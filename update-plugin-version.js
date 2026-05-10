const fs = require('fs');
const path = require('path');

const packageJson = require('./package.json');

const newVersion = packageJson.version;
const pluginFile = path.join(__dirname, 'sesamy2.php'); // adjust file name if needed

// Strip a leading `^` / `~` / `>=` etc. from a semver range so we end up
// with a concrete version we can pin in PHP. Falls back to the original
// string if it doesn't look like a simple range.
function exactSemver(range) {
	if (typeof range !== 'string') return null;
	const match = range.match(/(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/);
	return match ? match[1] : range;
}

const sesamyJsRange =
	(packageJson.dependencies && packageJson.dependencies['@sesamy/sesamy-js']) ||
	(packageJson.devDependencies && packageJson.devDependencies['@sesamy/sesamy-js']);
const sesamyJsVersion = exactSemver(sesamyJsRange);

fs.readFile(pluginFile, 'utf8', (err, data) => {
	if (err) {
		return console.error(err);
	}
	let result = data;
	// Update version in the header comment
	result = result.replace(/(Version:\s+)([0-9]+\.[0-9]+\.[0-9]+)/, `$1${newVersion}`);
	// Update version in the define constant
	result = result.replace(
		/(define\(\s*'SESAMY_PLUGIN_VERSION',\s*')([0-9]+\.[0-9]+\.[0-9]+)(')/,
		`$1${newVersion}$3`,
	);
	// Sync the pinned `@sesamy/sesamy-js` version so the bootstrap loader
	// and the script-host chain stay in lockstep with the dep we resolved.
	if (sesamyJsVersion) {
		result = result.replace(
			/(define\(\s*'SESAMY_JS_VERSION',\s*')([^']+)(')/,
			`$1${sesamyJsVersion}$3`,
		);
	}

	fs.writeFile(pluginFile, result, 'utf8', (err) => {
		if (err) return console.error(err);
		console.log(`Plugin file updated to version ${newVersion}`);
		if (sesamyJsVersion) {
			console.log(`SESAMY_JS_VERSION set to ${sesamyJsVersion}`);
		}
		return true;
	});
	return true;
});
