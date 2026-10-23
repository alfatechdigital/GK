param(
    [ValidateRange(1024, 65535)]
    [int] $Port = 8765
)

$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $phpCommand) {
    throw 'PHP tidak ditemukan. Pasang PHP 8.1 atau lebih baru dan aktifkan ekstensi pdo_sqlite serta fileinfo.'
}

Write-Host "Situs: http://127.0.0.1:$Port/"
Write-Host "Admin: http://127.0.0.1:$Port/admin"
Write-Host 'Tekan Ctrl+C untuk menghentikan server.'
& $phpCommand.Source -d upload_max_filesize=8M -d post_max_size=160M -S "127.0.0.1:$Port" router.php
