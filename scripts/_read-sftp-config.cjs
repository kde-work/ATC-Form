const fs = require('fs');
const path = require('path');

const cfgPath = path.join(__dirname, '..', '.vscode', 'sftp.json');

if (!fs.existsSync(cfgPath)) {
  process.stderr.write(`Missing config: ${cfgPath}\n`);
  process.exit(1);
}

const raw = fs.readFileSync(cfgPath, 'utf8');
let cfg;
try {
  cfg = JSON.parse(raw);
} catch (e) {
  process.stderr.write(`Invalid JSON in ${cfgPath}\n`);
  process.exit(1);
}

const host = String(cfg.host || '').trim();
const username = String(cfg.username || '').trim();
const remotePath = String(cfg.remotePath || '').trim();
const protocolRaw = String(cfg.protocol || 'sftp').trim().toLowerCase();
const protocol = protocolRaw === 'ftp' || protocolRaw === 'ftps' ? protocolRaw : 'sftp';
const defaultPort = protocol === 'sftp' ? 22 : 21;
const port = Number(cfg.port) > 0 ? Number(cfg.port) : defaultPort;
const privateKeyPath = cfg.privateKeyPath ? String(cfg.privateKeyPath).trim() : '';

if (!host || !username || !remotePath) {
  process.stderr.write('sftp.json must contain host, username, remotePath\n');
  process.exit(1);
}

process.stdout.write(
  [
    `HOST=${host}`,
    `PORT=${port}`,
    `PROTOCOL=${protocol}`,
    `SFTP_USER=${username}`,
    `REMOTE=${remotePath}`,
    `SFTP_KEY=${privateKeyPath}`,
  ].join('\n')
);
