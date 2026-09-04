const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const cfgPath = path.join(__dirname, '..', '.vscode', 'sftp.json');

if (!fs.existsSync(cfgPath)) {
  process.stderr.write(`Missing config: ${cfgPath}\n`);
  process.exit(1);
}

let cfg;
try {
  cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
} catch (e) {
  process.stderr.write(`Invalid JSON in ${cfgPath}\n`);
  process.exit(1);
}

const host = String(cfg.host || '').trim();
const username = String(cfg.username || '').trim();
const password = String(cfg.password || '');
const protocolRaw = String(cfg.protocol || 'ftp').trim().toLowerCase();
const protocol = protocolRaw === 'ftps' ? 'ftps' : 'ftp';
const port = Number(cfg.port) > 0 ? Number(cfg.port) : 21;

if (!host || !username) {
  process.stderr.write('sftp.json must contain host and username\n');
  process.exit(1);
}

const localArg = process.argv[2] ? path.resolve(process.argv[2]) : '';
const remoteArg = process.argv[3] || '';

if (!localArg || !fs.existsSync(localArg)) {
  process.stderr.write(`Local path not found: ${localArg}\n`);
  process.exit(1);
}

const curlBin = process.platform === 'win32' ? 'curl.exe' : 'curl';
const curlCheck = spawnSync(curlBin, ['--version'], { encoding: 'utf8' });
if (curlCheck.error && curlCheck.error.code === 'ENOENT') {
  process.stderr.write('curl is required for FTP upload\n');
  process.exit(1);
}

/**
 * @param {string} value
 * @returns {string}
 */
function stripSlashes(value) {
  return String(value || '')
    .replace(/\\/g, '/')
    .replace(/^\/+|\/+$/g, '');
}

/**
 * @param {string} dir
 * @returns {array<int,string>}
 */
function walkFiles(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...walkFiles(full));
    } else if (entry.isFile()) {
      out.push(full);
    }
  }
  return out;
}

/**
 * @param {string} localFile
 * @param {string} remoteFile
 */
function uploadFile(localFile, remoteFile) {
  const urlPath = stripSlashes(remoteFile);
  const url = `ftp://${host}:${port}/${urlPath}`;
  const args = ['-sS', '--fail', '--ftp-create-dirs', '--user', `${username}:${password}`];
  if (protocol === 'ftps') {
    args.push('--ssl');
  }
  args.push('-T', localFile, url);

  const result = spawnSync(curlBin, args, { encoding: 'utf8' });
  if (result.status !== 0) {
    const details = String(result.stderr || result.stdout || result.error || '').trim();
    process.stderr.write(`FTP upload failed: ${remoteFile}\n`);
    if (details) {
      process.stderr.write(`${details}\n`);
    }
    process.exit(result.status || 1);
  }
}

const localStat = fs.statSync(localArg);
if (localStat.isDirectory()) {
  const files = walkFiles(localArg);
  if (files.length === 0) {
    process.stderr.write(`No files in ${localArg}\n`);
    process.exit(1);
  }
  for (const file of files) {
    const rel = path.relative(localArg, file).split(path.sep).join('/');
    const remoteFile = [stripSlashes(remoteArg), rel].filter(Boolean).join('/');
    process.stdout.write(`  ${rel}\n`);
    uploadFile(file, remoteFile);
  }
} else {
  const remoteFile = stripSlashes(remoteArg) || path.basename(localArg);
  process.stdout.write(`  ${path.basename(localArg)}\n`);
  uploadFile(localArg, remoteFile);
}
