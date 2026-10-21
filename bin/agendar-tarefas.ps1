# Execute manualmente no PowerShell, com a conta que executará as tarefas.
# Registra somente duas tarefas locais da aplicação; não roda automaticamente ao iniciar o site.
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpExecutable = 'C:\xampp\php\php.exe'
if (!(Test-Path $phpExecutable)) { throw 'Ajuste phpExecutable para a instalação do seu PHP.' }
$monitorAction = New-ScheduledTaskAction -Execute $phpExecutable -Argument ('"' + (Join-Path $PSScriptRoot 'monitor.php') + '"') -WorkingDirectory $projectRoot
$backupAction = New-ScheduledTaskAction -Execute $phpExecutable -Argument ('"' + (Join-Path $PSScriptRoot 'backup.php') + '"') -WorkingDirectory $projectRoot
$monitorTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$backupTrigger = New-ScheduledTaskTrigger -Daily -At '02:00'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'SENN-AIX-Monitor' -Action $monitorAction -Trigger $monitorTrigger -Settings $settings -Description 'Alertas logísticos SENN AI-X'
Register-ScheduledTask -TaskName 'SENN-AIX-Backup' -Action $backupAction -Trigger $backupTrigger -Settings $settings -Description 'Backup SQL diário SENN AI-X'
Write-Host 'Tarefas registradas. Confira o histórico no Agendador e teste a restauração.'
