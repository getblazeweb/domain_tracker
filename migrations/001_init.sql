CREATE TABLE IF NOT EXISTS domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT,
    db_host TEXT,
    db_port TEXT,
    db_name TEXT,
    db_user TEXT,
    db_password_enc TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS subdomains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    file_location TEXT NOT NULL,
    description TEXT,
    db_host TEXT,
    db_port TEXT,
    db_name TEXT,
    db_user TEXT,
    db_password_enc TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);
