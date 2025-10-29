#Requires -RunAsAdministrator
Write-Host "[pre-make] Verificando 'make'..."
if (Get-Command make -ErrorAction SilentlyContinue) {
  Write-Host "[pre-make] 'make' já está instalado."
  exit 0
}

function Install-With-Choco {
  if (Get-Command choco -ErrorAction SilentlyContinue) {
    choco install make -y
    return $true
  }
  return $false
}

function Install-With-Scoop {
  if (Get-Command scoop -ErrorAction SilentlyContinue) {
    scoop install make
    return $true
  }
  return $false
}

function Install-With-Winget {
  if (Get-Command winget -ErrorAction SilentlyContinue) {
    winget install -e --id GnuWin32.Make -h 2>$null
    if ($LASTEXITCODE -eq 0) { return $true }
    winget install -e --id EZWinPorts.make -h 2>$null
    if ($LASTEXITCODE -eq 0) { return $true }
  }
  return $false
}

if (Install-With-Choco) { Write-Host "[pre-make] Instalado via Chocolatey."; exit 0 }
if (Install-With-Scoop) { Write-Host "[pre-make] Instalado via Scoop."; exit 0 }
if (Install-With-Winget){ Write-Host "[pre-make] Instalado via Winget."; exit 0 }

Write-Host "[pre-make] Não consegui instalar automaticamente."
Write-Host "Opções:"
Write-Host "  1) Chocolatey: choco install make"
Write-Host "  2) Scoop:       scoop install make"
Write-Host "  3) Winget:      winget install -e --id GnuWin32.Make"
exit 1
