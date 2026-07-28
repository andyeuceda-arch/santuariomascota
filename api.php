<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** Crea y administra la conexión MySQL/MariaDB del santuario. */
final class ConexionBD
{
    private PDO $pdo;

    public function __construct(string $host = '127.0.0.1', string $nombreBD = 'santuario', string $usuario = 'root', string $password = '')
    {
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $conexionBase = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $usuario, $password, $opciones);
            $conexionBase->exec('CREATE DATABASE IF NOT EXISTS ' . $nombreBD . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            $this->pdo = new PDO('mysql:host=' . $host . ';dbname=' . $nombreBD . ';charset=utf8mb4', $usuario, $password, $opciones);
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS mascotas (
                    ID INT(11) NOT NULL AUTO_INCREMENT,
                    nombre VARCHAR(255) NOT NULL,
                    especie VARCHAR(100) NOT NULL,
                    raza VARCHAR(255) NOT NULL,
                    edad INT(11) DEFAULT NULL,
                    peso_actual DECIMAL(10,2) NOT NULL CHECK (peso_actual > 0),
                    color_senas TEXT NOT NULL,
                    responsable VARCHAR(255) NOT NULL,
                    telefono_emergencia VARCHAR(50) NOT NULL,
                    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (ID)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
            );
        } catch (PDOException $error) {
            throw new RuntimeException('No se pudo conectar con la base de datos MySQL: ' . $error->getMessage());
        }
    }

    public function obtenerPDO(): PDO
    {
        return $this->pdo;
    }
}

function limpiarDato(mixed $dato): string
{
    return trim(strip_tags(stripslashes((string) $dato)));
}

class Mascota
{
    protected string $nombre;
    protected string $especie;
    protected string $raza;
    protected ?int $edad;
    protected float $pesoActual;
    protected string $colorSenas;
    protected string $responsable;
    protected string $telefonoEmergencia;

    public function __construct(
        string $nombre,
        string $especie,
        string $raza,
        ?int $edad,
        float $pesoActual,
        string $colorSenas,
        string $responsable,
        string $telefonoEmergencia
    ) {
        $this->setNombre($nombre);
        $this->setEspecie($especie);
        $this->setRaza($raza);
        $this->setEdad($edad);
        $this->setPesoActual($pesoActual);
        $this->setColorSenas($colorSenas);
        $this->setResponsable($responsable);
        $this->setTelefonoEmergencia($telefonoEmergencia);
    }

    public function getNombre(): string { return $this->nombre; }
    public function getEspecie(): string { return $this->especie; }
    public function getRaza(): string { return $this->raza; }
    public function getEdad(): ?int { return $this->edad; }
    public function getPesoActual(): float { return $this->pesoActual; }
    public function getColorSenas(): string { return $this->colorSenas; }
    public function getResponsable(): string { return $this->responsable; }
    public function getTelefonoEmergencia(): string { return $this->telefonoEmergencia; }

    public function setNombre(string $valor): void { $this->nombre = $this->requerido($valor, 'nombre'); }
    public function setEspecie(string $valor): void { $this->especie = $this->requerido($valor, 'especie'); }
    public function setRaza(string $valor): void { $this->raza = $this->requerido($valor, 'raza'); }
    public function setEdad(?int $valor): void
    {
        if ($valor !== null && ($valor < 0 || $valor > 50)) {
            throw new InvalidArgumentException('La edad debe estar entre 0 y 50 años.');
        }
        $this->edad = $valor;
    }
    public function setPesoActual(float $valor): void
    {
        if (!is_numeric($valor) || $valor <= 0) {
            throw new InvalidArgumentException('El peso debe ser numérico y mayor que cero.');
        }
        $this->pesoActual = $valor;
    }
    public function setColorSenas(string $valor): void { $this->colorSenas = $this->requerido($valor, 'color o señas físicas'); }
    public function setResponsable(string $valor): void { $this->responsable = $this->requerido($valor, 'responsable'); }
    public function setTelefonoEmergencia(string $valor): void { $this->telefonoEmergencia = $this->requerido($valor, 'teléfono de emergencia'); }

    private function requerido(string $valor, string $campo): string
    {
        if ($valor === '') {
            throw new InvalidArgumentException('El campo ' . $campo . ' es obligatorio.');
        }
        return $valor;
    }
}

final class RegistroMascota extends Mascota
{
    public function guardar(PDO $pdo): int
    {
        $consulta = $pdo->prepare(
            'INSERT INTO mascotas
            (nombre, especie, raza, edad, peso_actual, color_senas, responsable, telefono_emergencia)
            VALUES (:nombre, :especie, :raza, :edad, :peso_actual, :color_senas, :responsable, :telefono_emergencia)'
        );
        $consulta->execute([
            ':nombre' => $this->getNombre(),
            ':especie' => $this->getEspecie(),
            ':raza' => $this->getRaza(),
            ':edad' => $this->getEdad(),
            ':peso_actual' => $this->getPesoActual(),
            ':color_senas' => $this->getColorSenas(),
            ':responsable' => $this->getResponsable(),
            ':telefono_emergencia' => $this->getTelefonoEmergencia(),
        ]);
        return (int) $pdo->lastInsertId();
    }
}

try {
    $conexion = new ConexionBD();
    $pdo = $conexion->obtenerPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $consulta = $pdo->query('SELECT ID, nombre, especie, raza, edad, peso_actual, color_senas, responsable, telefono_emergencia, creado_en FROM mascotas ORDER BY ID DESC');
        echo json_encode(['ok' => true, 'mascotas' => $consulta->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $entrada = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $peso = $entrada['peso_actual'] ?? null;
    if (!is_numeric($peso) || (float) $peso <= 0) {
        throw new InvalidArgumentException('El peso debe ser numérico y mayor que cero.');
    }

    $mascota = new RegistroMascota(
        limpiarDato($entrada['nombre'] ?? ''),
        limpiarDato($entrada['especie'] ?? ''),
        limpiarDato($entrada['raza'] ?? ''),
        ($entrada['edad'] ?? '') === '' ? null : (int) limpiarDato($entrada['edad']),
        (float) $peso,
        limpiarDato($entrada['color_senas'] ?? ''),
        limpiarDato($entrada['responsable'] ?? ''),
        limpiarDato($entrada['telefono_emergencia'] ?? '')
    );

    $id = $mascota->guardar($pdo);
    http_response_code(201);
    echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Mascota registrada correctamente.'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Ocurrió un error al guardar la mascota.'], JSON_UNESCAPED_UNICODE);
}
