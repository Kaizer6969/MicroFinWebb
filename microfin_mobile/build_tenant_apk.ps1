param(
    [Parameter(Mandatory = $true)]
    [string]$TenantId,

    [Parameter(Mandatory = $true)]
    [string]$AppName,

    [string]$OutputSlug = "",
    [switch]$ReplaceGeneric
)

$ErrorActionPreference = "Stop"

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectDir = Join-Path $scriptRoot "microfin_mobile"
$outputDir = Join-Path $scriptRoot "tenant_apks"

if ([string]::IsNullOrWhiteSpace($OutputSlug)) {
    $OutputSlug = $TenantId
}

$safeSlug = ($OutputSlug.ToLowerInvariant() -replace '[^a-z0-9_-]', '_').Trim('_')
if ([string]::IsNullOrWhiteSpace($safeSlug)) {
    throw "Unable to derive a valid output slug from '$OutputSlug'."
}

if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir | Out-Null
}

$flutterArgs = @(
    "build",
    "apk",
    "--release",
    "--dart-define=TENANT_ID=$TenantId",
    "--dart-define=APP_NAME=$AppName"
)

Push-Location $projectDir
try {
    & flutter @flutterArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Flutter build failed with exit code $LASTEXITCODE."
    }

    $builtApk = Join-Path $projectDir "build\app\outputs\flutter-apk\app-release.apk"
    if (-not (Test-Path $builtApk)) {
        throw "Build completed but app-release.apk was not found."
    }

    $tenantApk = Join-Path $outputDir "$safeSlug.apk"
    Copy-Item -LiteralPath $builtApk -Destination $tenantApk -Force

    if ($ReplaceGeneric) {
        $genericApk = Join-Path $scriptRoot "microfin_app.apk"
        Copy-Item -LiteralPath $builtApk -Destination $genericApk -Force
    }

    Write-Host "Built tenant APK:" $tenantApk
} finally {
    Pop-Location
}
