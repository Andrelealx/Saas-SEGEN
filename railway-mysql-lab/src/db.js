const mysql = require("mysql2/promise");

function buildConnectionConfig() {
  const databaseUrl = process.env.DATABASE_URL || process.env.MYSQL_URL;

  if (databaseUrl) {
    const parsed = new URL(databaseUrl);
    const databaseName = parsed.pathname.replace(/^\/+/, "");

    if (!databaseName) {
      throw new Error("DATABASE_URL sem nome de banco no path.");
    }

    return {
      host: parsed.hostname,
      port: Number(parsed.port || 3306),
      user: decodeURIComponent(parsed.username),
      password: decodeURIComponent(parsed.password),
      database: databaseName,
    };
  }

  const {
    MYSQLHOST,
    MYSQLPORT,
    MYSQLUSER,
    MYSQLPASSWORD,
    MYSQLDATABASE,
  } = process.env;

  if (!MYSQLHOST || !MYSQLUSER || !MYSQLDATABASE) {
    throw new Error(
      "Defina DATABASE_URL (ou MYSQL_URL) ou as variaveis MYSQLHOST, MYSQLUSER e MYSQLDATABASE.",
    );
  }

  return {
    host: MYSQLHOST,
    port: Number(MYSQLPORT || 3306),
    user: MYSQLUSER,
    password: MYSQLPASSWORD || "",
    database: MYSQLDATABASE,
  };
}

const pool = mysql.createPool({
  ...buildConnectionConfig(),
  connectionLimit: 5,
});

async function pingDatabase() {
  const [rows] = await pool.query("SELECT 1 AS ok");
  return rows[0]?.ok === 1;
}

async function initSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS tasks (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      done TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `);
}

async function listTasks() {
  const [rows] = await pool.query(
    "SELECT id, title, done, created_at FROM tasks ORDER BY id DESC LIMIT 100",
  );
  return rows;
}

async function createTask(title) {
  const [result] = await pool.query("INSERT INTO tasks (title) VALUES (?)", [title]);
  const [rows] = await pool.query(
    "SELECT id, title, done, created_at FROM tasks WHERE id = ?",
    [result.insertId],
  );
  return rows[0];
}

async function closePool() {
  await pool.end();
}

module.exports = {
  initSchema,
  pingDatabase,
  listTasks,
  createTask,
  closePool,
};
