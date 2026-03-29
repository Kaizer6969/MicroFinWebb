# ==============================================================================
# Initial Bulk Upload to InfinityFree
# ==============================================================================
# This script uploads ALL files from your Fundline project to InfinityFree
# Use this ONCE for initial setup, then use auto_sync.ps1 for ongoing changes
# ==============================================================================

# FTP Configuration
$FTP_HOST = "ftpupload.net"
$FTP_USER = "if0_40983103"
$FTP_PASS = "d5kInRIzkY8"
$FTP_REMOTE_DIR = "/htdocs"

# Local Configuration
$LOCAL_DIR = "c:\xampp\htdocs\Fundline"
$SYNC_IGNORE_FILE = ".syncignore"
$LOG_FILE = "upload_log.txt"

# ==============================================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Initial Bulk Upload to InfinityFree" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Load exclusion patterns
$excludePatterns = @()
if (Test-Path "$LOCAL_DIR\$SYNC_IGNORE_FILE") {
    $excludePatterns = Get-Content "$LOCAL_DIR\$SYNC_IGNORE_FILE" | Where-Object { 
        $_ -notmatch '^\s*#' -and $_ -notmatch '^\s*$' 
    }
    Write-Host "Loaded $($excludePatterns.Count) exclusion patterns" -ForegroundColor Green
}

# Function to check if file should be excluded
function Should-Exclude {
    param($filePath)
    
    $relativePath = $filePath.Replace($LOCAL_DIR, "").TrimStart('\')
    
    foreach ($pattern in $excludePatterns) {
        $pattern = $pattern.Trim()
        
        if ($pattern.EndsWith('/')) {
            if ($relativePath -like "$($pattern.TrimEnd('/'))*") {
                return $true
            }
        }
        elseif ($pattern -like "*`**") {
            if ($relativePath -like $pattern) {
                return $true
            }
        }
        else {
            if ($relativePath -eq $pattern -or $relativePath -like "*\$pattern") {
                return $true
            }
        }
    }
    
    return $false
}

# Function to create remote directory
function Create-RemoteDirectory {
    param($remotePath)
    
    try {
        $ftpUri = "ftp://$FTP_HOST$remotePath"
        $request = [System.Net.FtpWebRequest]::Create($ftpUri)
        $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $request.KeepAlive = $false
        
        $response = $request.GetResponse()
        $response.Close()
        return $true
    }
    catch {
        # Directory might already exist, that's okay
        return $false
    }
}

# Function to upload file
function Upload-File {
    param($localFile, $remoteFile)
    
    try {
        $ftpUri = "ftp://$FTP_HOST$remoteFile"
        $request = [System.Net.FtpWebRequest]::Create($ftpUri)
        $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.UseBinary = $true
        $request.KeepAlive = $false
        
        $fileContent = [System.IO.File]::ReadAllBytes($localFile)
        $request.ContentLength = $fileContent.Length
        
        $requestStream = $request.GetRequestStream()
        $requestStream.Write($fileContent, 0, $fileContent.Length)
        $requestStream.Close()
        
        $response = $request.GetResponse()
        $response.Close()
        
        return $true
    }
    catch {
        Write-Host "ERROR: $_" -ForegroundColor Red
        return $false
    }
}

# Test FTP connection
Write-Host "Testing FTP connection..." -ForegroundColor Yellow
try {
    $testUri = "ftp://$FTP_HOST$FTP_REMOTE_DIR"
    $request = [System.Net.FtpWebRequest]::Create($testUri)
    $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
    $request.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
    $response = $request.GetResponse()
    $response.Close()
    Write-Host "FTP connection successful!" -ForegroundColor Green
    Write-Host ""
}
catch {
    Write-Host "FTP connection failed: $_" -ForegroundColor Red
    pause
    exit
}

# Get all files
Write-Host "Scanning files..." -ForegroundColor Yellow
$allFiles = Get-ChildItem -Path $LOCAL_DIR -Recurse -File
$filesToUpload = @()

foreach ($file in $allFiles) {
    if (-not (Should-Exclude $file.FullName)) {
        $filesToUpload += $file
    }
}

Write-Host "Found $($filesToUpload.Count) files to upload" -ForegroundColor Green
Write-Host ""

# Confirm upload
Write-Host "Ready to upload $($filesToUpload.Count) files to InfinityFree" -ForegroundColor Cyan
$confirm = Read-Host "Continue? (Y/N)"
if ($confirm -ne "Y" -and $confirm -ne "y") {
    Write-Host "Upload cancelled" -ForegroundColor Yellow
    exit
}

Write-Host ""
Write-Host "Starting upload..." -ForegroundColor Cyan
Write-Host ""

$uploadedCount = 0
$errorCount = 0
$createdDirs = @{}

foreach ($file in $filesToUpload) {
    $relativePath = $file.FullName.Replace($LOCAL_DIR, "").Replace('\', '/')
    $remoteFile = "$FTP_REMOTE_DIR$relativePath"
    
    # Create remote directory if needed
    $remoteDir = Split-Path $remoteFile -Parent
    if (-not $createdDirs.ContainsKey($remoteDir)) {
        Create-RemoteDirectory $remoteDir | Out-Null
        $createdDirs[$remoteDir] = $true
    }
    
    # Upload file
    Write-Host "Uploading: $relativePath" -ForegroundColor Gray
    if (Upload-File $file.FullName $remoteFile) {
        $uploadedCount++
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Add-Content -Path "$LOCAL_DIR\$LOG_FILE" -Value "[$timestamp] UPLOADED: $relativePath"
    }
    else {
        $errorCount++
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Upload Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Uploaded: $uploadedCount files" -ForegroundColor Green
Write-Host "Errors: $errorCount files" -ForegroundColor $(if ($errorCount -gt 0) { "Red" } else { "Green" })
Write-Host ""
Write-Host "Log saved to: $LOG_FILE" -ForegroundColor Yellow
Write-Host ""
pause
