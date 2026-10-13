import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import express from 'express';
import { join } from 'node:path';
import fs from 'node:fs';
import initSqlJs, { Database } from 'sql.js';
import bcrypt from 'bcryptjs';
import multer from 'multer';
import sharp from 'sharp';

const browserDistFolder = join(process.cwd(), 'dist/app/browser');
const dataDir = join(process.cwd(), 'data');
const uploadsDir = join(process.cwd(), 'uploads', 'photos');
const dbPath = join(dataDir, 'phortal.db');

if (!fs.existsSync(dataDir)) fs.mkdirSync(dataDir, { recursive: true });
if (!fs.existsSync(uploadsDir)) fs.mkdirSync(uploadsDir, { recursive: true });

let db: Database;

async function getDb(): Promise<Database> {
  if (db) return db;
  const SQL = await initSqlJs();
  if (fs.existsSync(dbPath)) {
    const filebuffer = fs.readFileSync(dbPath);
    db = new SQL.Database(filebuffer);
  } else {
    db = new SQL.Database();
  }
  return db;
}

function saveDb() {
  if (db) {
    const data = db.export();
    const buffer = Buffer.from(data);
    fs.writeFileSync(dbPath, buffer);
  }
}

async function dbRun(sql: string, params: any[] = []): Promise<any> {
  const database = await getDb();
  database.run(sql, params);
  saveDb();
}

async function dbAll(sql: string, params: any[] = []): Promise<any[]> {
  const database = await getDb();
  const stmt = database.prepare(sql);
  stmt.bind(params);
  const results: any[] = [];
  while (stmt.step()) {
    results.push(stmt.getAsObject());
  }
  stmt.free();
  return results;
}

async function dbGet(sql: string, params: any[] = []): Promise<any> {
  const database = await getDb();
  const stmt = database.prepare(sql);
  stmt.bind(params);
  let result = null;
  if (stmt.step()) {
    result = stmt.getAsObject();
  }
  stmt.free();
  return result;
}

async function initDb() {
  await dbRun(`CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin', 'curator')),
    full_name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );`);

  await dbRun(`CREATE TABLE IF NOT EXISTS galleries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    code TEXT UNIQUE NOT NULL,
    is_private INTEGER DEFAULT 1,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );`);

  await dbRun(`CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gallery_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    title TEXT,
    caption TEXT,
    file_size INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );`);

  await dbRun(`CREATE TABLE IF NOT EXISTS watermarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gallery_id INTEGER UNIQUE NOT NULL,
    text TEXT NOT NULL DEFAULT 'PHORTAL COPYRIGHT',
    opacity INTEGER NOT NULL DEFAULT 50,
    font_size INTEGER NOT NULL DEFAULT 24,
    position TEXT NOT NULL DEFAULT 'bottom-right',
    color_hex TEXT NOT NULL DEFAULT '#FFFFFF',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );`);

  await dbRun(`CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT NOT NULL,
    action TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id TEXT,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );`);

  const userCount = await dbGet('SELECT COUNT(*) as count FROM users');
  if (!userCount || userCount.count === 0) {
    const adminHash = bcrypt.hashSync('admin123', 10);
    const curatorHash = bcrypt.hashSync('curator123', 10);

    await dbRun(
      `INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', 'System Administrator')`,
      ['admin', adminHash]
    );
    await dbRun(
      `INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'curator', 'Lead Gallery Curator')`,
      ['curator', curatorHash]
    );

    await dbRun(
      `INSERT INTO galleries (title, description, code, is_private, created_by) VALUES (?, ?, ?, 1, 1)`,
      ['Monolithic Form & Shadow', 'Minimalist architectural photography exploring geometric concrete monoliths in mist.', 'ARCH2026']
    );
    await dbRun(
      `INSERT INTO galleries (title, description, code, is_private, created_by) VALUES (?, ?, ?, 0, 2)`,
      ['Cascade & Moss', 'Fine art long-exposure study of waterfalls in rainforest sanctuaries.', 'NAT889']
    );

    await dbRun(
      `INSERT INTO watermarks (gallery_id, text, opacity, font_size, position) VALUES (1, 'PHORTAL ARCH2026 © ADMIN', 60, 28, 'bottom-right')`
    );
    await dbRun(
      `INSERT INTO watermarks (gallery_id, text, opacity, font_size, position) VALUES (2, 'CURATOR FINE ART COLLECTION', 50, 24, 'tile')`
    );

    await seedSamplePhotos();

    await dbRun(
      `INSERT INTO audit_logs (user_id, username, action, target_type, target_id, details, ip_address) VALUES (1, 'system', 'SYSTEM_BOOTSTRAP', 'DATABASE', '0', 'Database bootstrapped with default accounts, galleries, and audit trail.', '127.0.0.1')`
    );
  }
}

async function seedSamplePhotos() {
  const samples = [
    {
      filename: 'sample_arch1.jpg',
      title: 'Brutalist Concrete Library in Morning Mist',
      gallery_id: 1,
      bg: '#1e293b',
      accent: '#64748b',
      text: 'MONOLITH NO. 01'
    },
    {
      filename: 'sample_arch2.jpg',
      title: 'Geometric Spiral Atrium in Soft Natural Light',
      gallery_id: 1,
      bg: '#0f172a',
      accent: '#475569',
      text: 'MONOLITH NO. 02'
    },
    {
      filename: 'sample_nat1.jpg',
      title: 'Emerald Cascade Waterfall in Ancient Rain Forest',
      gallery_id: 2,
      bg: '#064e3b',
      accent: '#10b981',
      text: 'CASCADE NO. 01'
    },
    {
      filename: 'sample_nat2.jpg',
      title: 'Alpine Ridge at Dawn Twilight',
      gallery_id: 2,
      bg: '#1e1b4b',
      accent: '#6366f1',
      text: 'SANCTUARY NO. 02'
    }
  ];

  for (const s of samples) {
    const filePath = join(uploadsDir, s.filename);
    if (!fs.existsSync(filePath)) {
      const svg = `<svg width="1920" height="1080" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="${s.bg}" />
            <stop offset="100%" stop-color="${s.accent}" />
          </linearGradient>
        </defs>
        <rect width="100%" height="100%" fill="url(#g)" />
        <circle cx="960" cy="540" r="300" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" />
        <text x="960" y="520" font-family="sans-serif" font-size="48" font-weight="600" fill="#ffffff" text-anchor="middle" letter-spacing="4">${s.text}</text>
        <text x="960" y="580" font-family="sans-serif" font-size="20" fill="rgba(255,255,255,0.7)" text-anchor="middle">PHORTAL EXHIBITION ARCHIVE</text>
      </svg>`;
      await sharp(Buffer.from(svg)).jpeg({ quality: 90 }).toFile(filePath);

      await dbRun(
        `INSERT INTO photos (gallery_id, filename, original_name, title, caption, file_size, width, height) VALUES (?, ?, ?, ?, 'Fine art photography print study.', 150000, 1920, 1080)`,
        [s.gallery_id, s.filename, s.filename, s.title]
      );
    }
  }
}

initDb().catch(console.error);

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => {
    cb(null, uploadsDir);
  },
  filename: (_req, file, cb) => {
    const ext = file.originalname.split('.').pop() || 'jpg';
    const name = `${Date.now()}_${Math.random().toString(36).substring(2, 10)}.${ext}`;
    cb(null, name);
  }
});
const upload = multer({
  storage,
  limits: { fileSize: 25 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    if (file.mimetype.startsWith('image/')) {
      cb(null, true);
    } else {
      cb(new Error('Only image files are allowed'));
    }
  }
});

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use('/uploads/photos', express.static(uploadsDir));

async function audit(userId: number | null, username: string, action: string, targetType: string, targetId: string, details: string, req: express.Request) {
  const ip = req.ip || req.socket.remoteAddress || '127.0.0.1';
  await dbRun(
    `INSERT INTO audit_logs (user_id, username, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [userId, username, action, targetType, targetId, details, ip]
  );
}

// Authentication
app.post('/api/auth/login', async (req, res): Promise<any> => {
  const { username, password } = req.body;
  if (!username || !password) {
    return res.status(400).json({ error: 'Username and password required' });
  }

  const user = await dbGet('SELECT * FROM users WHERE username = ?', [username]);
  if (user && bcrypt.compareSync(password, user.password_hash)) {
    await audit(user.id, user.username, 'USER_LOGIN', 'USER', String(user.id), `Logged in as ${user.role}`, req);
    return res.json({
      success: true,
      user: {
        id: user.id,
        username: user.username,
        role: user.role,
        full_name: user.full_name
      }
    });
  } else {
    await audit(null, username || 'guest', 'LOGIN_FAILED', 'USER', '0', 'Failed authentication attempt', req);
    return res.status(401).json({ error: 'Invalid username or password' });
  }
});

app.post('/api/auth/logout', async (_req, res): Promise<any> => {
  return res.json({ success: true });
});

app.get('/api/auth/status', async (_req, res): Promise<any> => {
  return res.json({ authenticated: false, user: null });
});

// Galleries
app.get('/api/galleries', async (req, res): Promise<any> => {
  const id = req.query['id'];
  const code = (req.query['code'] as string) || '';

  if (id) {
    const gallery = await dbGet(
      `SELECT g.*, u.full_name as curator_name, w.text as watermark_text, w.opacity, w.font_size, w.position, w.color_hex 
       FROM galleries g 
       LEFT JOIN users u ON g.created_by = u.id 
       LEFT JOIN watermarks w ON g.id = w.gallery_id 
       WHERE g.id = ?`,
      [id]
    );

    if (!gallery) {
      return res.status(404).json({ error: 'Gallery not found' });
    }

    if (gallery.is_private) {
      if (!code || gallery.code.toUpperCase() !== code.trim().toUpperCase()) {
        return res.status(403).json({ error: 'Gallery code required', is_private: true });
      }
    }

    const photos = await dbAll('SELECT * FROM photos WHERE gallery_id = ? ORDER BY id DESC', [id]);
    gallery.photos = photos;
    return res.json({ gallery });
  } else {
    const galleries = await dbAll(
      `SELECT g.*, u.full_name as curator_name, (SELECT COUNT(*) FROM photos WHERE gallery_id = g.id) as photo_count 
       FROM galleries g 
       LEFT JOIN users u ON g.created_by = u.id 
       ORDER BY g.created_at DESC`
    );
    return res.json({ galleries });
  }
});

app.post('/api/galleries/verify-code', async (req, res): Promise<any> => {
  const { code } = req.body;
  if (!code) return res.status(400).json({ error: 'Code required' });

  const gallery = await dbGet('SELECT * FROM galleries WHERE UPPER(code) = UPPER(?)', [code.trim()]);
  if (gallery) {
    await audit(null, 'guest_visitor', 'GUEST_ACCESS_GRANTED', 'GALLERY', String(gallery.id), `Unlocked gallery '${gallery.title}'`, req);
    return res.json({ success: true, gallery_id: gallery.id, title: gallery.title, code: gallery.code });
  } else {
    await audit(null, 'guest_visitor', 'GUEST_ACCESS_DENIED', 'GALLERY', '0', `Invalid code '${code}'`, req);
    return res.status(400).json({ error: 'Invalid gallery access code' });
  }
});

app.post('/api/galleries', async (req, res): Promise<any> => {
  const { title, description, code, is_private, user_id, username } = req.body;
  if (!title || !code) {
    return res.status(400).json({ error: 'Title and code are required' });
  }

  try {
    const uppercaseCode = code.trim().toUpperCase();
    await dbRun(
      'INSERT INTO galleries (title, description, code, is_private, created_by) VALUES (?, ?, ?, ?, ?)',
      [title, description || '', uppercaseCode, is_private ? 1 : 0, user_id || 1]
    );

    const inserted = await dbGet('SELECT id FROM galleries WHERE code = ?', [uppercaseCode]);
    const galleryId = inserted ? inserted.id : 1;
    const watermarkText = `© ${title.toUpperCase()} - PHORTAL`;
    await dbRun(
      'INSERT INTO watermarks (gallery_id, text, opacity, font_size, position) VALUES (?, ?, 50, 24, "bottom-right")',
      [galleryId, watermarkText]
    );

    await audit(user_id || 1, username || 'admin', 'CREATE_GALLERY', 'GALLERY', String(galleryId), `Created gallery '${title}' with code '${uppercaseCode}'`, req);
    return res.json({ success: true, gallery_id: galleryId });
  } catch (err: any) {
    return res.status(400).json({ error: 'Access code already exists or database error.' });
  }
});

app.put('/api/galleries', async (req, res): Promise<any> => {
  const { id, title, description, code, is_private, username } = req.body;
  if (!id || !title || !code) {
    return res.status(400).json({ error: 'ID, title, and code required' });
  }

  await dbRun('UPDATE galleries SET title = ?, description = ?, code = ?, is_private = ? WHERE id = ?', [
    title,
    description || '',
    code.trim().toUpperCase(),
    is_private ? 1 : 0,
    id
  ]);

  await audit(1, username || 'admin', 'UPDATE_GALLERY', 'GALLERY', String(id), `Updated gallery '${title}'`, req);
  return res.json({ success: true });
});

app.delete('/api/galleries/:id', async (req, res): Promise<any> => {
  const galleryId = req.params['id'];
  const photos = await dbAll('SELECT filename FROM photos WHERE gallery_id = ?', [galleryId]);
  for (const p of photos) {
    const fp = join(uploadsDir, p.filename);
    if (fs.existsSync(fp)) fs.unlinkSync(fp);
  }

  await dbRun('DELETE FROM galleries WHERE id = ?', [galleryId]);
  await audit(1, 'admin', 'DELETE_GALLERY', 'GALLERY', galleryId, `Deleted gallery ID ${galleryId} and files`, req);
  return res.json({ success: true });
});

// Photo Uploads & Deletion
app.post('/api/photos/upload', upload.single('photo'), async (req, res): Promise<any> => {
  if (!req.file) return res.status(400).json({ error: 'No photo file provided' });

  const galleryId = req.body.gallery_id;
  const title = req.body.title || req.file.originalname;
  const caption = req.body.caption || '';

  const metadata = await sharp(req.file.path).metadata();
  const width = metadata.width || 1200;
  const height = metadata.height || 800;

  await dbRun(
    'INSERT INTO photos (gallery_id, filename, original_name, title, caption, file_size, width, height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [galleryId, req.file.filename, req.file.originalname, title, caption, req.file.size, width, height]
  );

  const inserted = await dbGet('SELECT id FROM photos WHERE filename = ?', [req.file.filename]);
  const photoId = inserted ? inserted.id : 1;

  await audit(1, 'curator', 'UPLOAD_PHOTO', 'PHOTO', String(photoId), `Uploaded '${title}' (${width}x${height})`, req);
  return res.json({
    success: true,
    photo: {
      id: photoId,
      filename: req.file.filename,
      title,
      width,
      height
    }
  });
});

app.delete('/api/photos/:id', async (req, res): Promise<any> => {
  const photo = await dbGet('SELECT * FROM photos WHERE id = ?', [req.params['id']]);
  if (photo) {
    const fp = join(uploadsDir, photo.filename);
    if (fs.existsSync(fp)) fs.unlinkSync(fp);
    await dbRun('DELETE FROM photos WHERE id = ?', [req.params['id']]);
    await audit(1, 'curator', 'DELETE_PHOTO', 'PHOTO', req.params['id'], `Deleted photo '${photo.title}'`, req);
  }
  return res.json({ success: true });
});

// Watermark Settings
app.post('/api/watermark', async (req, res): Promise<any> => {
  const { gallery_id, text, opacity, font_size, position, color_hex } = req.body;
  if (!gallery_id) return res.status(400).json({ error: 'Gallery ID required' });

  const existing = await dbGet('SELECT * FROM watermarks WHERE gallery_id = ?', [gallery_id]);
  if (existing) {
    await dbRun(
      'UPDATE watermarks SET text = ?, opacity = ?, font_size = ?, position = ?, color_hex = ?, updated_at = CURRENT_TIMESTAMP WHERE gallery_id = ?',
      [text, opacity || 50, font_size || 24, position || 'bottom-right', color_hex || '#FFFFFF', gallery_id]
    );
  } else {
    await dbRun(
      'INSERT INTO watermarks (gallery_id, text, opacity, font_size, position, color_hex) VALUES (?, ?, ?, ?, ?, ?)',
      [gallery_id, text, opacity || 50, font_size || 24, position || 'bottom-right', color_hex || '#FFFFFF']
    );
  }

  await audit(1, 'curator', 'UPDATE_WATERMARK', 'WATERMARK', String(gallery_id), `Updated watermark to '${text}' (${opacity}%)`, req);
  return res.json({ success: true });
});

// User Management
app.get('/api/users', async (_req, res): Promise<any> => {
  const users = await dbAll('SELECT id, username, role, full_name, created_at FROM users ORDER BY id DESC');
  return res.json({ users });
});

app.post('/api/users', async (req, res): Promise<any> => {
  const { username, password, role, full_name } = req.body;
  if (!username || !password || !full_name) {
    return res.status(400).json({ error: 'Username, password, and full name required' });
  }

  try {
    const hash = bcrypt.hashSync(password, 10);
    await dbRun('INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)', [
      username,
      hash,
      role || 'curator',
      full_name
    ]);

    const inserted = await dbGet('SELECT id FROM users WHERE username = ?', [username]);
    const newId = inserted ? inserted.id : 1;

    await audit(1, 'admin', 'CREATE_USER', 'USER', String(newId), `Created user '${username}' as ${role}`, req);
    return res.json({ success: true, id: newId });
  } catch (err: any) {
    return res.status(400).json({ error: 'Username already exists' });
  }
});

app.put('/api/users', async (req, res): Promise<any> => {
  const { id, role, full_name } = req.body;
  await dbRun('UPDATE users SET role = ?, full_name = ? WHERE id = ?', [role, full_name, id]);
  await audit(1, 'admin', 'UPDATE_USER', 'USER', String(id), `Updated user ID ${id} to ${role}`, req);
  return res.json({ success: true });
});

app.delete('/api/users/:id', async (req, res): Promise<any> => {
  await dbRun('DELETE FROM users WHERE id = ?', [req.params['id']]);
  await audit(1, 'admin', 'DELETE_USER', 'USER', req.params['id'], `Deleted user ID ${req.params['id']}`, req);
  return res.json({ success: true });
});

// Audit Logs
app.get('/api/audit', async (_req, res): Promise<any> => {
  const logs = await dbAll('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 200');
  return res.json({ audit_logs: logs });
});

// Image Engine
app.get(['/api/images/:id', '/phortal/includes/watermark.php'], async (req, res): Promise<any> => {
  const id = req.params['id'] || req.query['id'];
  const reqW = req.query['w'] ? parseInt(req.query['w'] as string, 10) : 0;
  const reqH = req.query['h'] ? parseInt(req.query['h'] as string, 10) : 0;
  const code = (req.query['code'] as string) || '';

  if (!id) return res.status(400).send('Photo ID required');

  const photo = await dbGet(
    'SELECT p.*, g.is_private, g.code as gallery_code FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?',
    [id]
  );

  if (!photo) return res.status(404).send('Photo not found');

  if (photo.is_private) {
    if (!code || photo.gallery_code.toUpperCase() !== code.trim().toUpperCase()) {
      return res.status(403).send('Gallery code required');
    }
  }

  const filePath = join(uploadsDir, photo.filename);
  if (!fs.existsSync(filePath)) return res.status(404).send('Image file missing');

  const watermark = await dbGet('SELECT * FROM watermarks WHERE gallery_id = ?', [photo.gallery_id]);
  const text = watermark ? watermark.text : 'PHORTAL COPYRIGHT';
  const opacity = watermark ? Math.min(100, Math.max(5, watermark.opacity)) : 50;
  const fontSize = watermark ? Math.min(72, Math.max(12, watermark.font_size)) : 24;
  const position = watermark ? watermark.position : 'bottom-right';

  let pipeline = sharp(filePath);
  const meta = await pipeline.metadata();
  const origW = meta.width || 1200;
  const origH = meta.height || 800;

  let targetW = origW;
  let targetH = origH;
  if (reqW > 0 && reqH > 0) {
    targetW = reqW;
    targetH = reqH;
  } else if (reqW > 0) {
    targetW = reqW;
    targetH = Math.round((origH / origW) * reqW);
  } else if (reqH > 0) {
    targetH = reqH;
    targetW = Math.round((origW / origH) * reqH);
  }

  pipeline = pipeline.resize(targetW, targetH, { fit: 'inside' });

  const alphaVal = opacity / 100;
  let gravity = 'southeast';
  if (position === 'center') gravity = 'centre';
  if (position === 'top-left') gravity = 'northwest';
  if (position === 'top-right') gravity = 'northeast';
  if (position === 'bottom-left') gravity = 'southwest';

  let svgOverlay = '';
  if (position === 'tile') {
    svgOverlay = `<svg width="${targetW}" height="${targetH}">
      <style>
        .wt { font-family: sans-serif; font-size: ${fontSize}px; fill: rgba(255,255,255,${alphaVal}); font-weight: bold; }
      </style>
      <pattern id="wmPattern" width="300" height="150" patternUnits="userSpaceOnUse" patternTransform="rotate(-25)">
        <text x="10" y="50" class="wt">${text}</text>
      </pattern>
      <rect width="100%" height="100%" fill="url(#wmPattern)" />
    </svg>`;
  } else {
    svgOverlay = `<svg width="${targetW}" height="${targetH}">
      <style>
        .wt { font-family: sans-serif; font-size: ${fontSize}px; fill: rgba(255,255,255,${alphaVal}); font-weight: bold; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.8)); }
      </style>
      <text x="50%" y="90%" text-anchor="middle" class="wt">${text}</text>
    </svg>`;
  }

  pipeline = pipeline.composite([
    {
      input: Buffer.from(svgOverlay),
      gravity: gravity as any
    }
  ]);

  res.setHeader('Content-Type', 'image/jpeg');
  res.setHeader('Cache-Control', 'public, max-age=86400');
  const buffer = await pipeline.jpeg({ quality: 85 }).toBuffer();
  return res.send(buffer);
});

app.use(
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false,
    redirect: false
  })
);

app.use((req, res, next) => {
  const angularApp = new AngularNodeAppEngine();
  angularApp
    .handle(req)
    .then((response) => (response ? writeResponseToNodeResponse(response, res) : next()))
    .catch(next);
});

if (isMainModule(import.meta.url) || process.env['pm_id']) {
  const port = process.env['PORT'] || 3000;
  app.listen(port, () => {
    console.log(`Phortal Node/Express server running on http://localhost:${port}`);
  });
}

export const reqHandler = createNodeRequestHandler(app);
