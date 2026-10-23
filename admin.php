<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
startAdminSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        if (!adminPasswordConfigured()) {
            $_SESSION['login_error'] = 'Password admin belum dikonfigurasi di environment server (GK_ADMIN_PASSWORD).';
        } elseif (adminLoginBlocked()) {
            $_SESSION['login_error'] = 'Terlalu banyak percobaan. Coba lagi dalam lima menit.';
        } elseif (authenticateAdmin($_POST['password'] ?? null)) {
            redirectToAdmin();
        } else {
            $_SESSION['login_error'] = 'Password tidak sesuai.';
        }
    } catch (DomainException $error) {
        $_SESSION['login_error'] = $error->getMessage();
    }
    redirectToAdmin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout'
    && isAdminAuthenticated()) {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
    logoutAdmin();
    redirectToAdmin();
}

if (!isAdminAuthenticated()) {
    $loginError = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    ?>
    <!doctype html>
    <html lang="id">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#0d2a20">
      <title>Login Admin | Gelar Karya Informatika 1</title>
      <link rel="stylesheet" href="/admin.css">
    </head>
    <body>
      <header class="admin-header">
        <a class="admin-brand" href="/"><span class="brand-mark">GK</span><span><strong>Admin Gelar Karya</strong><small>INFORMATIKA 1 · UM</small></span></a>
        <a class="back-link" href="/">← Lihat halaman utama</a>
      </header>
      <main class="admin-main login-main">
        <section class="admin-intro">
          <p class="eyebrow">PANEL PENGELOLAAN</p>
          <h1>Masuk ke <em>admin.</em></h1>
          <p>Masukkan password untuk mengelola konten kelas.</p>
        </section>
        <?php if (is_string($loginError) && $loginError !== ''): ?>
          <div class="flash flash-error" role="alert"><?= h($loginError) ?></div>
        <?php endif; ?>
        <section class="form-panel login-panel" aria-labelledby="login-title">
          <div class="panel-heading"><span class="panel-number">GK</span><div><h2 id="login-title">Login admin</h2><p>Sesi akan disimpan dengan cookie yang aman.</p></div></div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="login">
            <label for="admin-password">Password</label>
            <input id="admin-password" name="password" type="password" autocomplete="current-password" required autofocus>
            <button class="primary-button" type="submit">Masuk <span aria-hidden="true">↗</span></button>
          </form>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$database = appDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadedPhotos = [];
    $uploadedMemberPhotos = [];
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_project') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '' || mb_strlen($title) > 100) {
                throw new DomainException('Judul proyek wajib diisi dan maksimal 100 karakter.');
            }
            if ($description === '' || mb_strlen($description) > 3000) {
                throw new DomainException('Deskripsi proyek wajib diisi dan maksimal 3.000 karakter.');
            }

            $memberInputs = $_POST['members'] ?? [];
            if (!is_array($memberInputs) || count($memberInputs) > GK_MAX_MEMBERS) {
                throw new DomainException('Setiap proyek dapat memiliki maksimal 12 anggota.');
            }
            $memberFileErrors = $_FILES['member_photos']['error'] ?? [];
            if (!is_array($memberFileErrors)) {
                throw new DomainException('Data unggahan foto anggota tidak valid.');
            }
            $members = [];
            foreach ($memberInputs as $index => $memberInput) {
                $memberIndex = filter_var((string) $index, FILTER_VALIDATE_INT);
                if (!is_array($memberInput) || $memberIndex === false || $memberIndex < 0 || $memberIndex > 10000) {
                    throw new DomainException('Data anggota proyek tidak valid.');
                }

                $nameInput = $memberInput['name'] ?? '';
                $portfolioInput = $memberInput['portfolio_url'] ?? '';
                if (!is_string($nameInput) || !is_string($portfolioInput)) {
                    throw new DomainException('Nama dan tautan anggota harus berupa teks.');
                }
                $name = trim($nameInput);
                $portfolioUrl = trim($portfolioInput);
                $photoError = (int) ($memberFileErrors[$index] ?? UPLOAD_ERR_NO_FILE);
                if ($name === '' && $portfolioUrl === '' && $photoError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($name === '' || mb_strlen($name) > 100) {
                    throw new DomainException('Nama setiap anggota wajib diisi dan maksimal 100 karakter.');
                }
                $scheme = strtolower((string) parse_url($portfolioUrl, PHP_URL_SCHEME));
                if (mb_strlen($portfolioUrl) > 500
                    || filter_var($portfolioUrl, FILTER_VALIDATE_URL) === false
                    || !in_array($scheme, ['http', 'https'], true)) {
                    throw new DomainException('Setiap anggota harus memiliki tautan portofolio http:// atau https:// yang valid.');
                }
                $members[$index] = ['name' => $name, 'portfolio_url' => $portfolioUrl];
            }

            $uploadedPhotos = uploadProjectPhotos($_FILES['photos'] ?? null);
            $members = uploadMemberPhotos($_FILES['member_photos'] ?? null, $members);
            $uploadedMemberPhotos = array_column($members, 'photo_filename');
            createProject($database, $title, $description, $uploadedPhotos, $members);
            $uploadedPhotos = [];
            $uploadedMemberPhotos = [];
            setFlash('success', 'Proyek, foto, dan anggota berhasil disimpan.');
        } elseif ($action === 'update_project') {
            $projectId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($projectId === false || $projectId === null || $projectId < 1) {
                throw new DomainException('Proyek yang akan diedit tidak valid.');
            }

            $projectToUpdate = null;
            foreach (loadProjects($database) as $existingProject) {
                if ((int) $existingProject['id'] === $projectId) {
                    $projectToUpdate = $existingProject;
                    break;
                }
            }
            if ($projectToUpdate === null) {
                throw new DomainException('Proyek yang akan diedit tidak ditemukan.');
            }

            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '' || mb_strlen($title) > 100) {
                throw new DomainException('Judul proyek wajib diisi dan maksimal 100 karakter.');
            }
            if ($description === '' || mb_strlen($description) > 3000) {
                throw new DomainException('Deskripsi proyek wajib diisi dan maksimal 3.000 karakter.');
            }

            $memberInputs = $_POST['members'] ?? [];
            if (!is_array($memberInputs) || count($memberInputs) > GK_MAX_MEMBERS) {
                throw new DomainException('Setiap proyek dapat memiliki maksimal 12 anggota.');
            }
            $existingMembers = [];
            foreach ($projectToUpdate['members'] as $existingMember) {
                $existingMembers[(int) $existingMember['id']] = $existingMember;
            }

            $members = [];
            $retainedMemberIds = [];
            foreach ($memberInputs as $index => $memberInput) {
                $memberIndex = filter_var((string) $index, FILTER_VALIDATE_INT);
                if (!is_array($memberInput) || $memberIndex === false || $memberIndex < 0 || $memberIndex > 10000) {
                    throw new DomainException('Data anggota proyek tidak valid.');
                }

                $memberIdInput = $memberInput['id'] ?? '';
                $nameInput = $memberInput['name'] ?? '';
                $portfolioInput = $memberInput['portfolio_url'] ?? '';
                if (!is_string($memberIdInput) || !is_string($nameInput) || !is_string($portfolioInput)) {
                    throw new DomainException('Data anggota harus berupa teks yang valid.');
                }
                $memberId = $memberIdInput === '' ? 0 : filter_var($memberIdInput, FILTER_VALIDATE_INT);
                if ($memberId === false || $memberId < 0 || ($memberId > 0 && !isset($existingMembers[$memberId]))) {
                    throw new DomainException('Anggota yang dipilih bukan bagian dari proyek ini.');
                }
                if ($memberId > 0 && isset($retainedMemberIds[$memberId])) {
                    throw new DomainException('Anggota proyek tidak boleh dikirim lebih dari satu kali.');
                }

                $name = trim($nameInput);
                $portfolioUrl = trim($portfolioInput);
                if ($name === '' || mb_strlen($name) > 100) {
                    throw new DomainException('Nama setiap anggota wajib diisi dan maksimal 100 karakter.');
                }
                $scheme = strtolower((string) parse_url($portfolioUrl, PHP_URL_SCHEME));
                if (mb_strlen($portfolioUrl) > 500
                    || filter_var($portfolioUrl, FILTER_VALIDATE_URL) === false
                    || !in_array($scheme, ['http', 'https'], true)) {
                    throw new DomainException('Setiap anggota harus memiliki tautan portofolio http:// atau https:// yang valid.');
                }

                $member = ['id' => $memberId, 'name' => $name, 'portfolio_url' => $portfolioUrl];
                if ($memberId > 0) {
                    $member['photo_filename'] = $existingMembers[$memberId]['photo_filename'];
                    $retainedMemberIds[$memberId] = true;
                }
                $members[$memberIndex] = $member;
            }

            $members = uploadMemberPhotos($_FILES['member_photos'] ?? null, $members);
            $uploadedMemberPhotos = array_values(array_diff(
                array_column($members, 'photo_filename'),
                array_column($projectToUpdate['members'], 'photo_filename')
            ));

            $projectPhotoErrors = $_FILES['photos']['error'] ?? [];
            if (!is_array($projectPhotoErrors)) {
                throw new DomainException('Data foto proyek tidak valid.');
            }
            $hasReplacementPhotos = array_filter(
                $projectPhotoErrors,
                static fn (int $error): bool => $error !== UPLOAD_ERR_NO_FILE
            ) !== [];
            $uploadedPhotos = $hasReplacementPhotos ? uploadProjectPhotos($_FILES['photos'] ?? null) : [];
            updateProject(
                $database,
                $projectId,
                $title,
                $description,
                $hasReplacementPhotos ? $uploadedPhotos : null,
                array_values($members)
            );
            $uploadedPhotos = [];
            $uploadedMemberPhotos = [];
            setFlash('success', 'Perubahan proyek, foto, dan anggota berhasil disimpan.');
        } elseif ($action === 'add_student') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $portfolioUrl = trim((string) ($_POST['portfolio_url'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new DomainException('Nama mahasiswa wajib diisi dan maksimal 100 karakter.');
            }
            if ($portfolioUrl !== '') {
                $scheme = strtolower((string) parse_url($portfolioUrl, PHP_URL_SCHEME));
                if (mb_strlen($portfolioUrl) > 500
                    || filter_var($portfolioUrl, FILTER_VALIDATE_URL) === false
                    || !in_array($scheme, ['http', 'https'], true)) {
                    throw new DomainException('Tautan portofolio harus berupa URL http:// atau https:// yang valid.');
                }
            }

            $insert = $database->prepare('INSERT INTO students (name, portfolio_url) VALUES (:name, :portfolio_url)');
            $insert->execute(['name' => $name, 'portfolio_url' => $portfolioUrl]);
            setFlash('success', 'Identitas mahasiswa berhasil disimpan.');
        } elseif ($action === 'delete_project') {
            $projectId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($projectId === false || $projectId === null || $projectId < 1) {
                throw new DomainException('Proyek yang dipilih tidak valid.');
            }
            deleteProject($database, $projectId);
            setFlash('success', 'Proyek dan foto terkait berhasil dihapus.');
        } elseif ($action === 'delete_student') {
            $studentId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId === null || $studentId < 1) {
                throw new DomainException('Anggota yang dipilih tidak valid.');
            }
            deleteStudent($database, $studentId);
            setFlash('success', 'Identitas mahasiswa berhasil dihapus.');
        } else {
            throw new DomainException('Tindakan admin tidak dikenali.');
        }
    } catch (DomainException $error) {
        if ($uploadedPhotos !== []) {
            deleteProjectPhotoFiles($uploadedPhotos);
        }
        if ($uploadedMemberPhotos !== []) {
            deleteMemberPhotoFiles($uploadedMemberPhotos);
        }
        setFlash('error', $error->getMessage());
    } catch (Throwable $error) {
        if ($uploadedPhotos !== []) {
            deleteProjectPhotoFiles($uploadedPhotos);
        }
        if ($uploadedMemberPhotos !== []) {
            deleteMemberPhotoFiles($uploadedMemberPhotos);
        }
        error_log('Gelar Karya admin: ' . $error);
        setFlash('error', 'Perubahan gagal disimpan. Periksa log server lalu coba lagi.');
    }

    redirectToAdmin();
}

$projects = loadProjects($database);
$flash = takeFlash();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d2a20">
  <title>Admin | Gelar Karya Informatika 1</title>
  <link rel="stylesheet" href="/admin.css">
  <script src="/admin.js" defer></script>
</head>
<body>
  <header class="admin-header">
    <a class="admin-brand" href="/"><span class="brand-mark">GK</span><span><strong>Admin Gelar Karya</strong><small>INFORMATIKA 1 · UM</small></span></a>
    <div class="admin-header-actions">
      <a class="back-link" href="/">← Lihat halaman utama</a>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="logout-button" type="submit">Keluar</button>
      </form>
    </div>
  </header>
  <main class="admin-main">
    <section class="admin-intro">
      <p class="eyebrow">PANEL PENGELOLAAN</p>
      <h1>Kelola <em>konten kelas.</em></h1>
      <p>Proyek, foto, dan anggota tiap proyek tersimpan di database aplikasi.</p>
      <div class="local-notice"><strong>Akses admin terlindungi.</strong> Pastikan situs menggunakan HTTPS saat dipublikasikan.</div>
    </section>

    <?php if ($flash !== null): ?>
      <div class="flash flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="admin-forms">
      <section class="form-panel" aria-labelledby="project-form-title">
        <div class="panel-heading"><span class="panel-number">01</span><div><h2 id="project-form-title">Tambahkan proyek</h2><p>Judul, deskripsi, foto, dan maksimal 12 anggota untuk satu karya kelas.</p></div></div>
        <?php if (count($projects) >= GK_MAX_PROJECTS): ?>
          <div class="limit-notice">Tiga proyek sudah terisi. Hapus satu proyek di daftar bawah untuk menggantinya.</div>
        <?php else: ?>
          <form method="post" enctype="multipart/form-data" data-project-form data-form-key="new">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="add_project">
            <label for="project-title">Judul proyek</label>
            <input id="project-title" name="title" type="text" maxlength="100" placeholder="Contoh: Jelajah Mahakam" required>
            <label for="project-description">Deskripsi</label>
            <textarea id="project-description" name="description" maxlength="3000" rows="5" placeholder="Ceritakan ide dan tujuan proyek..." required></textarea>
            <label for="project-photos">Foto proyek <span class="field-hint">Pilih 1–6 foto · JPG, PNG, GIF, atau WebP · maks. 8 MB/foto</span></label>
            <input id="project-photos" class="project-photo-input" name="photos[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
            <div class="photo-preview project-photo-preview" aria-live="polite"></div>
            <fieldset class="members-fieldset">
              <legend>Anggota proyek <span class="field-hint">Opsional · maksimal 12 anggota · foto maks. 8 MB</span></legend>
              <div class="project-members" data-next-index="0"></div>
              <button class="secondary-button add-project-member" type="button">+ Tambahkan anggota <span>(0/12)</span></button>
            </fieldset>
            <button class="primary-button" type="submit">Simpan proyek <span aria-hidden="true">↗</span></button>
          </form>
        <?php endif; ?>
      </section>
    </div>

    <section class="saved-content" aria-labelledby="saved-content-title">
      <div class="panel-heading"><span class="panel-number">02</span><div><h2 id="saved-content-title">Konten tersimpan</h2><p>Ubah detail, foto, dan anggota proyek kapan saja.</p></div><a class="refresh-link" href="/admin">Muat ulang ↻</a></div>
      <div class="saved-grid">
        <section class="saved-list" aria-labelledby="saved-projects-title">
          <h3 id="saved-projects-title">Proyek <span><?= h((string) count($projects)) ?>/<?= GK_MAX_PROJECTS ?></span></h3>
          <?php if ($projects === []): ?>
            <p class="empty-list">Belum ada proyek.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($projects as $project): ?>
                <li class="saved-project-item">
                  <div class="saved-item">
                    <div><strong><?= h($project['title']) ?></strong><small><?= h((string) count($project['photos'])) ?> foto · <?= h((string) count($project['members'])) ?> anggota</small></div>
                    <div class="saved-actions">
                      <button class="edit-button" type="button" data-toggle-edit>✎ Edit</button>
                      <form method="post" data-confirm="Hapus proyek ini beserta foto proyek dan semua foto anggotanya?">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete_project">
                        <input type="hidden" name="id" value="<?= h((string) $project['id']) ?>">
                        <button class="delete-button" type="submit" aria-label="Hapus proyek <?= h($project['title']) ?>">Hapus</button>
                      </form>
                    </div>
                  </div>
                  <form class="project-edit-form" data-project-form data-form-key="edit-<?= h((string) $project['id']) ?>" method="post" enctype="multipart/form-data" hidden>
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_project">
                    <input type="hidden" name="id" value="<?= h((string) $project['id']) ?>">
                    <label for="project-title-<?= h((string) $project['id']) ?>">Judul proyek</label>
                    <input id="project-title-<?= h((string) $project['id']) ?>" name="title" type="text" maxlength="100" value="<?= h($project['title']) ?>" required>
                    <label for="project-description-<?= h((string) $project['id']) ?>">Deskripsi</label>
                    <textarea id="project-description-<?= h((string) $project['id']) ?>" name="description" maxlength="3000" rows="5" required><?= h($project['description']) ?></textarea>
                    <label for="project-photos-<?= h((string) $project['id']) ?>">Ganti semua foto proyek <span class="field-hint">Opsional · pilih 1–6 foto · maksimal 8 MB/foto</span></label>
                    <div class="current-photos" aria-label="Foto proyek saat ini">
                      <?php foreach ($project['photos'] as $photo): ?>
                        <img src="<?= h(projectPhotoUrl((string) $photo['filename'])) ?>" alt="Foto proyek <?= h($project['title']) ?>">
                      <?php endforeach; ?>
                    </div>
                    <input id="project-photos-<?= h((string) $project['id']) ?>" class="project-photo-input" name="photos[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    <div class="photo-preview project-photo-preview" aria-live="polite"></div>
                    <fieldset class="members-fieldset">
                      <legend>Anggota proyek <span class="field-hint">Maksimal 12 anggota</span></legend>
                      <div class="project-members" data-next-index="<?= h((string) count($project['members'])) ?>">
                        <?php foreach ($project['members'] as $memberIndex => $member): ?>
                          <section class="member-form-card">
                            <input type="hidden" name="members[<?= h((string) $memberIndex) ?>][id]" value="<?= h((string) $member['id']) ?>">
                            <div class="member-form-heading"><strong>Anggota <span data-member-number><?= h(str_pad((string) ($memberIndex + 1), 2, '0', STR_PAD_LEFT)) ?></span></strong><button class="delete-button" type="button" data-remove-member>Hapus</button></div>
                            <label for="member-name-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>">Nama anggota</label>
                            <input id="member-name-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>" name="members[<?= h((string) $memberIndex) ?>][name]" type="text" maxlength="100" value="<?= h($member['name']) ?>" required>
                            <label for="member-portfolio-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>">Link portofolio</label>
                            <input id="member-portfolio-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>" name="members[<?= h((string) $memberIndex) ?>][portfolio_url]" type="url" maxlength="500" value="<?= h($member['portfolio_url']) ?>" required>
                            <label for="member-photo-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>">Ganti foto anggota <span class="field-hint">Opsional · JPG, PNG, GIF, atau WebP</span></label>
                            <input id="member-photo-<?= h((string) $project['id']) ?>-<?= h((string) $memberIndex) ?>" name="member_photos[<?= h((string) $memberIndex) ?>]" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="member-photo-preview" data-member-preview><img src="<?= h($member['photo_url']) ?>" alt="Foto <?= h($member['name']) ?>"></div>
                          </section>
                        <?php endforeach; ?>
                      </div>
                      <button class="secondary-button add-project-member" type="button">+ Tambahkan anggota <span>(<?= h((string) count($project['members'])) ?>/12)</span></button>
                    </fieldset>
                    <button class="primary-button" type="submit">Simpan perubahan <span aria-hidden="true">↗</span></button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>
    </section>
  </main>
  <footer class="admin-footer"><a class="admin-brand" href="/"><span class="brand-mark">GK</span><span><strong>Gelar Karya</strong><small>Informatika 1 · UM</small></span></a><p>Perubahan disimpan lokal di database SQLite.</p><a href="/">Kembali ke situs ↗</a></footer>
  <template id="project-member-template">
    <section class="member-form-card">
      <input type="hidden" name="members[__INDEX__][id]" value="">
      <div class="member-form-heading"><strong>Anggota <span data-member-number></span></strong><button class="delete-button" type="button" data-remove-member>Hapus</button></div>
      <label for="member-name-__FORM__-__INDEX__">Nama anggota</label>
      <input id="member-name-__FORM__-__INDEX__" name="members[__INDEX__][name]" type="text" maxlength="100" placeholder="Nama lengkap" required>
      <label for="member-portfolio-__FORM__-__INDEX__">Link portofolio</label>
      <input id="member-portfolio-__FORM__-__INDEX__" name="members[__INDEX__][portfolio_url]" type="url" maxlength="500" placeholder="https://portofolio.example" required>
      <label for="member-photo-__FORM__-__INDEX__">Foto anggota <span class="field-hint">JPG, PNG, GIF, atau WebP</span></label>
      <input id="member-photo-__FORM__-__INDEX__" name="member_photos[__INDEX__]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
      <div class="member-photo-preview" data-member-preview aria-live="polite"></div>
    </section>
  </template>
</body>
</html>
