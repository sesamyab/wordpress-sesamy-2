const fs = require('fs');
const path = require('path');

const packageJson = require('./package.json');

const newVersion = packageJson.version;
const pluginFile = path.join(__dirname, 'sesamy2.php'); // adjust file name if needed

// Strip a leading `^` / `~` / `>=` etc. from a semver range so we end up
// with a concrete version we can pin in PHP. Returns null when the range
// isn't a recognisable strict semver so the caller can hard-fail rather
// than write something like "latest" into the PHP constant.
function exactSemver(range) {
	if (typeof range !== 'string') return null;
	const match = range.match(/(\d+\.\d+\.\d+(?:-[0-9A-Za-z-.]+)?(?:\+[0-9A-Za-z-.]+)?)/);
	return match ? match[1] : null;
}

const sesamyJsRange =
	(packageJson.dependencies && packageJson.dependencies['@sesamy/sesamy-js']) ||
	(packageJson.devDependencies && packageJson.devDependencies['@sesamy/sesamy-js']);
const sesamyJsVersion = exactSemver(sesamyJsRange);

// If `@sesamy/sesamy-js` is listed but the range isn't pinned to a strict
// semver, refuse to write SESAMY_JS_VERSION rather than silently emit the
// raw range (e.g. "latest", "*", or "^1.2.3" with no patch).
if (sesamyJsRange && sesamyJsVersion === null) {
	console.error(
		`Refusing to write SESAMY_JS_VERSION: @sesamy/sesamy-js range "${sesamyJsRange}" is not pinned to a strict semver. exactSemver rejected it.`,
	);
	process.exit(1);
}

fs.readFile(pluginFile, 'utf8', (err, data) => {
	if (err) {
		console.error(err);
		process.exit(1);
	}
	let result = data;
	// Update version in the header comment
	const semverPattern = '[0-9]+\\.[0-9]+\\.[0-9]+(?:-[0-9A-Za-z-.]+)?(?:\\+[0-9A-Za-z-.]+)?';
	const headerRe = new RegExp(`(Version:\\s+)(${semverPattern})`);
	const defineRe = new RegExp(
		`(define\\(\\s*'SESAMY_PLUGIN_VERSION',\\s*')(${semverPattern})(')`,
	);
	if (!headerRe.test(result)) {
		console.error(`Failed to match plugin header Version line in ${pluginFile}`);
		process.exit(1);
	}
	if (!defineRe.test(result)) {
		console.error(`Failed to match SESAMY_PLUGIN_VERSION define in ${pluginFile}`);
		process.exit(1);
	}
	result = result.replace(headerRe, `$1${newVersion}`);
	result = result.replace(defineRe, `$1${newVersion}$3`);

	// Sync the pinned `@sesamy/sesamy-js` version so the bootstrap loader
	// and the script-host chain stay in lockstep with the dep we resolved.
	if (sesamyJsVersion) {
		const jsRe = /(define\(\s*'SESAMY_JS_VERSION',\s*')([^']+)(')/;
		if (!jsRe.test(result)) {
			console.error(`Failed to match SESAMY_JS_VERSION define in ${pluginFile}`);
			process.exit(1);
		}
		result = result.replace(jsRe, `$1${sesamyJsVersion}$3`);
	}

	fs.writeFile(pluginFile, result, 'utf8', (writeErr) => {
		if (writeErr) {
			console.error(writeErr);
			process.exit(1);
		}
		console.log(`Plugin file updated to version ${newVersion}`);
		if (sesamyJsVersion) {
			console.log(`SESAMY_JS_VERSION set to ${sesamyJsVersion}`);
		}
	});
});
