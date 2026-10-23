<?php
declare(strict_types=1);

const GK_MAX_PROJECTS = 3;
const GK_MAX_PHOTOS = 6;
const GK_MAX_PHOTO_BYTES = 8 * 1024 * 1024;
const GK_MAX_MEMBERS = 12;
const GK_MAX_MEMBER_PHOTO_BYTES = 8 * 1024 * 1024;
const GK_ADMIN_PASSWORD = '2531537';

function appDatabase(): PDO
{
    static $database = null;
    if ($database instanceof PDO) {
        return $database;
    }

    $configuredPath = getenv('GK_DB_PATH');
    $databasePath = $configuredPath !== false && $configuredPath !== ''
        ? $configuredPath
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gelar-karya.sqlite3';
    $databaseDirectory = dirname($databasePath);

    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0750, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('Folder database tidak dapat dibuat.');
    }

    $database = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL CHECK(length(title) BETWEEN 1 AND 100),
            description TEXT NOT NULL CHECK(length(description) BETWEEN 1 AND 3000),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS project_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            filename TEXT NOT NULL UNIQUE
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL CHECK(length(name) BETWEEN 1 AND 100),
            portfolio_url TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS project_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            name TEXT NOT NULL CHECK(length(name) BETWEEN 1 AND 100),
            photo_filename TEXT NOT NULL UNIQUE,
            portfolio_url TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $database;
}

function loadProjects(PDO $database): array
{
    $projects = $database->query('SELECT id, title, description, created_at FROM projects ORDER BY id')->fetchAll();
    foreach ($projects as &$project) {
        $project['photos'] = [];
        $project['members'] = [];
    }
    unset($project);

    if ($projects === []) {
        return $projects;
    }

    $photos = $database->query(
        'SELECT project_id, filename FROM project_photos ORDER BY project_id, id'
    )->fetchAll();
    $projectIndexes = [];
    foreach ($projects as $index => $project) {
        $projectIndexes[(int) $project['id']] = $index;
    }
    foreach ($photos as $photo) {
        $projectId = (int) $photo['project_id'];
        if (isset($projectIndexes[$projectId])) {
            $projects[$projectIndexes[$projectId]]['photos'][] = $photo;
        }
    }

    $members = $database->query(
        'SELECT id, project_id, name, photo_filename, portfolio_url FROM project_members ORDER BY project_id, id'
    )->fetchAll();
    foreach ($members as $member) {
        $projectId = (int) $member['project_id'];
        if (isset($projectIndexes[$projectId])) {
            $member['photo_url'] = memberPhotoUrl((string) $member['photo_filename']);
            $projects[$projectIndexes[$projectId]]['members'][] = $member;
        }
    }

    return $projects;
}

function loadStudents(PDO $database): array
{
    return $database->query('SELECT id, name, portfolio_url FROM students ORDER BY id')->fetchAll();
}

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function projectPhotoUrl(string $filename): string
{
    return '/uploads/projects/' . rawurlencode(basename($filename));
}

function projectPhotoDirectory(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'projects';
}

function memberPhotoUrl(string $filename): string
{
    return '/uploads/members/' . rawurlencode(basename($filename));
}

function memberPhotoDirectory(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'members';
}

function uploadMemberPhotos(?array $files, array $members): array
{
    if ($members === []) {
        return [];
    }
    if (count($members) > GK_MAX_MEMBERS) {
        throw new DomainException('Setiap proyek dapat memiliki maksimal 12 anggota.');
    }
    if ($files === null || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])
        || !is_array($files['name'])) {
        throw new DomainException('Data foto anggota tidak valid. Pilih ulang foto anggota.');
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $storedFiles = [];

    if (!is_dir(memberPhotoDirectory())
        && !mkdir(memberPhotoDirectory(), 0755, true)
        && !is_dir(memberPhotoDirectory())) {
        throw new RuntimeException('Folder foto anggota tidak dapat dibuat.');
    }

    try {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($members as $index => &$member) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE && isset($member['photo_filename'])) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Ukuran foto anggota melebihi batas unggahan server.'
                    : ($error === UPLOAD_ERR_NO_FILE
                        ? 'Foto wajib diunggah untuk setiap anggota.'
                        : 'Salah satu foto anggota gagal diunggah. Coba pilih kembali.');
                throw new DomainException($message);
            }

            $temporaryPath = (string) ($files['tmp_name'][$index] ?? '');
            $fileSize = (int) ($files['size'][$index] ?? 0);
            if (!is_uploaded_file($temporaryPath)) {
                throw new DomainException('Foto anggota tidak dapat diverifikasi.');
            }
            if ($fileSize < 1 || $fileSize > GK_MAX_MEMBER_PHOTO_BYTES) {
                throw new DomainException('Setiap foto anggota harus berukuran maksimal 8 MB.');
            }

            $mimeType = $fileInfo->file($temporaryPath);
            if (!is_string($mimeType) || !isset($mimeExtensions[$mimeType])) {
                throw new DomainException('Gunakan foto anggota JPG, PNG, GIF, atau WebP.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mimeType];
            $destination = memberPhotoDirectory() . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($temporaryPath, $destination)) {
                throw new RuntimeException('Foto anggota tidak dapat disimpan.');
            }
            $storedFiles[] = $filename;
            $member['photo_filename'] = $filename;
        }
        unset($member);
    } catch (Throwable $error) {
        deleteMemberPhotoFiles($storedFiles);
        throw $error;
    }

    return $members;
}

function uploadProjectPhotos(?array $files): array
{
    if ($files === null || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])
        || !is_array($files['name'])) {
        throw new DomainException('Pilih satu sampai enam foto proyek.');
    }

    $fileCount = count($files['name']);
    if ($fileCount < 1 || $fileCount > GK_MAX_PHOTOS) {
        throw new DomainException('Setiap proyek harus memiliki satu sampai enam foto.');
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $storedFiles = [];

    if (!is_dir(projectPhotoDirectory())
        && !mkdir(projectPhotoDirectory(), 0755, true)
        && !is_dir(projectPhotoDirectory())) {
        throw new RuntimeException('Folder foto proyek tidak dapat dibuat.');
    }

    try {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        for ($index = 0; $index < $fileCount; $index++) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Ukuran foto melebihi batas unggahan server.'
                    : 'Salah satu foto gagal diunggah. Coba pilih kembali.';
                throw new DomainException($message);
            }

            $temporaryPath = (string) ($files['tmp_name'][$index] ?? '');
            $fileSize = (int) ($files['size'][$index] ?? 0);
            if (!is_uploaded_file($temporaryPath)) {
                throw new DomainException('Foto yang dipilih tidak dapat diverifikasi.');
            }
            if ($fileSize < 1 || $fileSize > GK_MAX_PHOTO_BYTES) {
                throw new DomainException('Setiap foto harus berukuran maksimal 8 MB.');
            }

            $mimeType = $fileInfo->file($temporaryPath);
            if (!is_string($mimeType) || !isset($mimeExtensions[$mimeType])) {
                throw new DomainException('Gunakan foto JPG, PNG, GIF, atau WebP.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mimeType];
            $destination = projectPhotoDirectory() . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($temporaryPath, $destination)) {
                throw new RuntimeException('Foto proyek tidak dapat disimpan.');
            }
            $storedFiles[] = $filename;
        }
    } catch (Throwable $error) {
        deleteProjectPhotoFiles($storedFiles);
        throw $error;
    }

    return $storedFiles;
}

function createProject(PDO $database, string $title, string $description, array $photos, array $members): void
{
    try {
        if (count($members) > GK_MAX_MEMBERS) {
            throw new DomainException('Setiap proyek dapat memiliki maksimal 12 anggota.');
        }
        $database->beginTransaction();
        $count = (int) $database->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        if ($count >= GK_MAX_PROJECTS) {
            throw new DomainException('Maksimal tiga proyek. Hapus proyek lama sebelum menambahkan yang baru.');
        }

        $insertProject = $database->prepare('INSERT INTO projects (title, description) VALUES (:title, :description)');
        $insertProject->execute(['title' => $title, 'description' => $description]);
        $projectId = (int) $database->lastInsertId();

        $insertPhoto = $database->prepare('INSERT INTO project_photos (project_id, filename) VALUES (:project_id, :filename)');
        foreach ($photos as $filename) {
            $insertPhoto->execute(['project_id' => $projectId, 'filename' => $filename]);
        }
        $insertMember = $database->prepare(
            'INSERT INTO project_members (project_id, name, photo_filename, portfolio_url)
             VALUES (:project_id, :name, :photo_filename, :portfolio_url)'
        );
        foreach ($members as $member) {
            $insertMember->execute([
                'project_id' => $projectId,
                'name' => $member['name'],
                'photo_filename' => $member['photo_filename'],
                'portfolio_url' => $member['portfolio_url'],
            ]);
        }
        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        deleteProjectPhotoFiles($photos);
        deleteMemberPhotoFiles(array_column($members, 'photo_filename'));
        throw $error;
    }
}

function updateProject(
    PDO $database,
    int $projectId,
    string $title,
    string $description,
    ?array $replacementPhotos,
    array $members
): void {
    if (count($members) > GK_MAX_MEMBERS) {
        throw new DomainException('Setiap proyek dapat memiliki maksimal 12 anggota.');
    }

    $projectQuery = $database->prepare('SELECT id FROM projects WHERE id = :id');
    $projectQuery->execute(['id' => $projectId]);
    if ($projectQuery->fetchColumn() === false) {
        throw new DomainException('Proyek yang akan diedit tidak ditemukan.');
    }

    $photoQuery = $database->prepare('SELECT filename FROM project_photos WHERE project_id = :project_id');
    $photoQuery->execute(['project_id' => $projectId]);
    $oldPhotos = array_column($photoQuery->fetchAll(), 'filename');

    $memberQuery = $database->prepare(
        'SELECT id, photo_filename FROM project_members WHERE project_id = :project_id'
    );
    $memberQuery->execute(['project_id' => $projectId]);
    $oldMembers = $memberQuery->fetchAll();
    $oldMembersById = [];
    foreach ($oldMembers as $oldMember) {
        $oldMembersById[(int) $oldMember['id']] = $oldMember;
    }

    $retainedMemberIds = [];
    $removedMemberPhotos = [];
    try {
        $database->beginTransaction();
        $updateProject = $database->prepare(
            'UPDATE projects SET title = :title, description = :description WHERE id = :id'
        );
        $updateProject->execute(['title' => $title, 'description' => $description, 'id' => $projectId]);

        if ($replacementPhotos !== null) {
            if ($replacementPhotos === [] || count($replacementPhotos) > GK_MAX_PHOTOS) {
                throw new DomainException('Pilih satu sampai enam foto proyek untuk mengganti foto lama.');
            }
            $deletePhotos = $database->prepare('DELETE FROM project_photos WHERE project_id = :project_id');
            $deletePhotos->execute(['project_id' => $projectId]);
            $insertPhoto = $database->prepare(
                'INSERT INTO project_photos (project_id, filename) VALUES (:project_id, :filename)'
            );
            foreach ($replacementPhotos as $filename) {
                $insertPhoto->execute(['project_id' => $projectId, 'filename' => $filename]);
            }
        }

        foreach ($members as $member) {
            $memberId = isset($member['id']) ? (int) $member['id'] : 0;
            if ($memberId > 0) {
                if (!isset($oldMembersById[$memberId]) || isset($retainedMemberIds[$memberId])) {
                    throw new DomainException('Daftar anggota proyek tidak valid. Muat ulang halaman dan coba lagi.');
                }
                $retainedMemberIds[$memberId] = true;
                $updateMember = $database->prepare(
                    'UPDATE project_members
                     SET name = :name, photo_filename = :photo_filename, portfolio_url = :portfolio_url
                     WHERE id = :id AND project_id = :project_id'
                );
                $updateMember->execute([
                    'name' => $member['name'],
                    'photo_filename' => $member['photo_filename'],
                    'portfolio_url' => $member['portfolio_url'],
                    'id' => $memberId,
                    'project_id' => $projectId,
                ]);
                if ($oldMembersById[$memberId]['photo_filename'] !== $member['photo_filename']) {
                    $removedMemberPhotos[] = $oldMembersById[$memberId]['photo_filename'];
                }
                continue;
            }

            $insertMember = $database->prepare(
                'INSERT INTO project_members (project_id, name, photo_filename, portfolio_url)
                 VALUES (:project_id, :name, :photo_filename, :portfolio_url)'
            );
            $insertMember->execute([
                'project_id' => $projectId,
                'name' => $member['name'],
                'photo_filename' => $member['photo_filename'],
                'portfolio_url' => $member['portfolio_url'],
            ]);
        }

        foreach ($oldMembersById as $oldMemberId => $oldMember) {
            if (!isset($retainedMemberIds[$oldMemberId])) {
                $removedMemberPhotos[] = $oldMember['photo_filename'];
            }
        }
        $deleteMembers = $database->prepare(
            'DELETE FROM project_members WHERE project_id = :project_id AND id = :id'
        );
        foreach (array_diff(array_keys($oldMembersById), array_keys($retainedMemberIds)) as $removedMemberId) {
            $deleteMembers->execute(['project_id' => $projectId, 'id' => $removedMemberId]);
        }

        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        if ($replacementPhotos !== null) {
            deleteProjectPhotoFiles($replacementPhotos);
        }
        deleteMemberPhotoFiles(array_values(array_diff(
            array_column($members, 'photo_filename'),
            array_column($oldMembers, 'photo_filename')
        )));
        throw $error;
    }

    if ($replacementPhotos !== null) {
        deleteProjectPhotoFiles($oldPhotos);
    }
    deleteMemberPhotoFiles($removedMemberPhotos);
}

function deleteProject(PDO $database, int $projectId): void
{
    $selectMembers = $database->prepare(
        'SELECT photo_filename FROM project_members WHERE project_id = :project_id'
    );
    $selectMembers->execute(['project_id' => $projectId]);
    $memberPhotos = array_column($selectMembers->fetchAll(), 'photo_filename');

    $selectPhotos = $database->prepare('SELECT filename FROM project_photos WHERE project_id = :project_id');
    $selectPhotos->execute(['project_id' => $projectId]);
    $photos = array_column($selectPhotos->fetchAll(), 'filename');

    $delete = $database->prepare('DELETE FROM projects WHERE id = :id');
    $delete->execute(['id' => $projectId]);
    deleteProjectPhotoFiles($photos);
    deleteMemberPhotoFiles($memberPhotos);
}

function deleteStudent(PDO $database, int $studentId): void
{
    $delete = $database->prepare('DELETE FROM students WHERE id = :id');
    $delete->execute(['id' => $studentId]);
}

function deleteProjectPhotoFiles(array $filenames): void
{
    foreach ($filenames as $filename) {
        $safeFilename = basename((string) $filename);
        if ($safeFilename !== (string) $filename) {
            error_log('Gelar Karya: foto dengan nama file tidak valid dilewati.');
            continue;
        }

        $path = projectPhotoDirectory() . DIRECTORY_SEPARATOR . $safeFilename;
        if (is_file($path) && !unlink($path)) {
            error_log('Gelar Karya: foto proyek tidak dapat dihapus: ' . $safeFilename);
        }
    }
}

function deleteMemberPhotoFiles(array $filenames): void
{
    foreach ($filenames as $filename) {
        $safeFilename = basename((string) $filename);
        if ($safeFilename !== (string) $filename) {
            error_log('Gelar Karya: foto anggota dengan nama file tidak valid dilewati.');
            continue;
        }

        $path = memberPhotoDirectory() . DIRECTORY_SEPARATOR . $safeFilename;
        if (is_file($path) && !unlink($path)) {
            error_log('Gelar Karya: foto anggota tidak dapat dihapus: ' . $safeFilename);
        }
    }
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('gelar_karya_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function isAdminAuthenticated(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function adminPasswordConfigured(): bool
{
    return GK_ADMIN_PASSWORD !== '';
}

function adminLoginBlocked(): bool
{
    return (int) ($_SESSION['admin_login_blocked_until'] ?? 0) > time();
}

function authenticateAdmin(mixed $password): bool
{
    if (!is_string($password)) {
        return false;
    }
    if (adminLoginBlocked()) {
        return false;
    }

    if (hash_equals(GK_ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        unset($_SESSION['admin_login_attempts'], $_SESSION['admin_login_blocked_until'], $_SESSION['csrf_token']);
        return true;
    }

    $attempts = (int) ($_SESSION['admin_login_attempts'] ?? 0) + 1;
    if ($attempts >= 5) {
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_login_blocked_until'] = time() + 300;
    } else {
        $_SESSION['admin_login_attempts'] = $attempts;
    }
    return false;
}

function logoutAdmin(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): void
{
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function redirectToAdmin(): never
{
    header('Location: /admin', true, 303);
    exit;
}
