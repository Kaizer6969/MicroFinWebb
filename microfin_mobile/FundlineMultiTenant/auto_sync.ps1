# ==============================================================================
# Fundline Auto-Sync to InfinityFree
# ==============================================================================
# This script automatically uploads changed files to InfinityFree via FTP
# whenever you save changes locally.
#
# SETUP:
# 1. Edit the FTP credentials section below
# 2. Run: powershell -ExecutionPolicy Bypass -File auto_sync.ps1
# 3. Keep this window open while developing
# ==============================================================================

# FTP Configuration - CONFIGURED WITH YOUR INFINITYFREE CREDENTIALS
$FTP_HOST = "ftpupload.net"              # Your FTP hostname
$FTP_USER = "if0_40983103"                # Your FTP username
$FTP_PASS = "d5kInRIzkY8"                 # Your FTP password
$FTP_REMOTE_DIR = "/htdocs"               # Remote directory (usually /htdocs)

# Local Configuration
$LOCAL_DIR = "c:\xampp\htdocs\Fundline"
$SYNC_IGNORE_FILE = ".syncignore"
$LOG_FILE = "sync_log.txt"

# ==============================================================================
# DO NOT EDIT BELOW THIS LINE
# ==============================================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Fundline Auto-Sync to InfinityFree" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# FTP credentials are configured and ready

# Load exclusion patterns
$excludePatterns = @()
if (Test-Path "$LOCAL_DIR\$SYNC_IGNORE_FILE") {
    $excludePatterns = Get-Content "$LOCAL_DIR\$SYNC_IGNORE_FILE" | Where-Object { 
        $_ -notmatch '^\s*#' -and $_ -notmatch '^\s*$' 
    }
    Write-Host "Loaded $($excludePatterns.Count) exclusion patterns from $SYNC_IGNORE_FILE" -ForegroundColor Green
}

# Function to check if file should be excluded
function Should-Exclude {
    param($filePath)
    
    $relativePath = $filePath.Replace($LOCAL_DIR, "").TrimStart('\')
    
    foreach ($pattern in $excludePatterns) {
        $pattern = $pattern.Trim()
        
        # Handle directory patterns
        if ($pattern.EndsWith('/')) {
            if ($relativePath -like "$($pattern.TrimEnd('/'))*") {
                return $true
            }
        }
        # Handle wildcard patterns
        elseif ($pattern -like "*`**") {
            if ($relativePath -like $pattern) {
                return $true
            }
        }
        # Handle exact matches
        else {
            if ($relativePath -eq $pattern -or $relativePath -like "*\$pattern") {
                return $true
            }
        }
    }
    
    return $false
}

# Function to upload file via FTP
function Upload-File {
    param($localFile)
    
    if (Should-Exclude $localFile) {
        Write-Host "SKIPPED (excluded): $localFile" -ForegroundColor Yellow
        return
    }
    
    try {
        $relativePath = $localFile.Replace($LOCAL_DIR, "").Replace('\', '/')
        $remoteFile = "$FTP_REMOTE_DIR$relativePath"
        
        # Create FTP request
        $ftpUri = "ftp://$FTP_HOST$remoteFile"
        $request = [System.Net.FtpWebRequest]::Create($ftpUri)
        $request.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.UseBinary = $true
        $request.KeepAlive = $false
        
        # Upload file
        $fileContent = [System.IO.File]::ReadAllBytes($localFile)
        $request.ContentLength = $fileContent.Length
        
        $requestStream = $request.GetRequestStream()
        $requestStream.Write($fileContent, 0, $fileContent.Length)
        $requestStream.Close()
        
        $response = $request.GetResponse()
        $response.Close()
        
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        $logEntry = "[$timestamp] UPLOADED: $relativePath"
        Add-Content -Path "$LOCAL_DIR\$LOG_FILE" -Value $logEntry
        
        Write-Host "UPLOADED: $relativePath" -ForegroundColor Green
    }
    catch {
        Write-Host "ERROR uploading $localFile : $_" -ForegroundColor Red
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
    Write-Host "Please check your FTP credentials and try again." -ForegroundColor Yellow
    pause
    exit
}

# Set up file watcher
Write-Host "Starting file watcher on: $LOCAL_DIR" -ForegroundColor Cyan
Write-Host "Monitoring for changes... (Press Ctrl+C to stop)" -ForegroundColor Cyan
Write-Host ""

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $LOCAL_DIR
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

# Define event handlers
$onChange = {
    $path = $Event.SourceEventArgs.FullPath
    $changeType = $Event.SourceEventArgs.ChangeType
    
    if ($changeType -eq "Changed" -or $changeType -eq "Created") {
        # Wait a bit to ensure file is fully written
        Start-Sleep -Milliseconds 500
        
        if (Test-Path $path -PathType Leaf) {
            Upload-File $path
        }
    }
}

# Register event handlers
Register-ObjectEvent $watcher "Changed" -Action $onChange | Out-Null
Register-ObjectEvent $watcher "Created" -Action $onChange | Out-Null

Write-Host "Auto-sync is now active! Make changes to your files and they will upload automatically." -ForegroundColor Green
Write-Host ""

# Keep script running
try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    $watcher.Dispose()
}
