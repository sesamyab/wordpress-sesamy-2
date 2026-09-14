const fs = require('fs');
const path = require('path');

const packageJson = require('./package.json');

const newVersion = packageJson.version;
const pluginFile = path.join(__dirname, 'sesamy2.php'); // adjust file name if needed

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

	fs.writeFile(pluginFile, result, 'utf8', (writeErr) => {
		if (writeErr) {
			console.error(writeErr);
			process.exit(1);
		}
		console.log(`Plugin file updated to version ${newVersion}`);
	});
});
