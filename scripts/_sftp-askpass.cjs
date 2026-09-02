const fs = require('fs');
const path = require('path');

const cfgPath = path.join(__dirname, '..', '.vscode', 'sftp.json');
const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
process.stdout.write(String(cfg.password || ''));
