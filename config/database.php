<?php

class Database {
    private static $instance = null;
    private $db;

    private function __construct() {
        $dbPath = __DIR__ . '/../data/agendamento.db';
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        try {
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->createTables();
        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->db;
    }

    private function createTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                senha TEXT NOT NULL,
                nome TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS agendamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome_servidor TEXT NOT NULL,
                email TEXT,
                pessoas_responsaveis TEXT NOT NULL,
                data_utilizacao DATE NOT NULL,
                horario_entrada TIME NOT NULL,
                horario_saida TIME NOT NULL,
                espaco TEXT NOT NULL,
                equipamentos TEXT,
                ocupa_todo_espaco INTEGER DEFAULT 0,
                status TEXT DEFAULT 'pendente',
                google_calendar_event_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        try {
            $this->db->exec("ALTER TABLE agendamentos ADD COLUMN email TEXT");
        } catch (PDOException $e) {
        }
        
        try {
            $this->db->exec("ALTER TABLE agendamentos ADD COLUMN motivo_rejeicao TEXT");
        } catch (PDOException $e) {
        }

        $stmt = $this->db->query("SELECT COUNT(*) FROM usuarios");
        if ($stmt->fetchColumn() == 0) {
            $emailAdmin = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'incubadora.grapetech@gmail.com';
            $senhaAdmin = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'ALTERE_ESTA_SENHA';
            $senhaHash = password_hash($senhaAdmin, PASSWORD_DEFAULT);
            $this->db->exec("
                INSERT INTO usuarios (email, senha, nome) 
                VALUES ('$emailAdmin', '$senhaHash', 'Administrador')
            ");
        }
    }
}
