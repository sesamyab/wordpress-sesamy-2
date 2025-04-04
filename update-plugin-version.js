const fs = require('fs');
const path = require('path');

const packageJson = require('./package.json');

const newVersion = packageJson.version;
const pluginFile = path.join(__dirname, 'sesamy2.php'); // adjust file name if needed

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

	fs.writeFile(pluginFile, result, 'utf8', (err) => {
		if (err) return console.error(err);
		console.log(`Plugin file updated to version ${newVersion}`);
		return true;
	});
	return true;
});
