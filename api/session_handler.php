<?php
// Simpan session di tabel MySQL (dipakai kalau SESSION_DRIVER=db, mis. di Vercel serverless).
// Tabel `sessions` dibuat otomatis kalau belum ada.

class DbSessionHandler implements SessionHandlerInterface
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS sessions (' .
            'id VARCHAR(128) NOT NULL PRIMARY KEY, ' .
            'data MEDIUMTEXT NOT NULL, ' .
            'expires INT UNSIGNED NOT NULL, ' .
            'INDEX idx_expires (expires))'
        );
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $now = time();
        $st = $this->db->prepare('SELECT data FROM sessions WHERE id = ? AND expires > ?');
        $st->bind_param('si', $id, $now);
        $st->execute();
        $st->bind_result($data);
        $out = $st->fetch() ? $data : '';
        $st->close();
        return (string) $out;
    }

    public function write($id, $data): bool
    {
        $exp = time() + 86400;
        $st = $this->db->prepare('REPLACE INTO sessions (id, data, expires) VALUES (?, ?, ?)');
        $st->bind_param('ssi', $id, $data, $exp);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function destroy($id): bool
    {
        $st = $this->db->prepare('DELETE FROM sessions WHERE id = ?');
        $st->bind_param('s', $id);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function gc($max_lifetime): int
    {
        $this->db->query('DELETE FROM sessions WHERE expires < ' . time());
        return 0;
    }
}
