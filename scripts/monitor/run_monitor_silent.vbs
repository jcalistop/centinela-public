' Ejecuta CentinelMonitoR.ps1 directamente (ventana oculta).
Set oShell = CreateObject("WScript.Shell")
Dim scriptDir
scriptDir = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\"))
Dim psCmd
psCmd = "powershell -NoProfile -ExecutionPolicy Bypass -File """ & scriptDir & "CentinelMonitoR.ps1"" -ConfigPath """ & scriptDir & "config\monitor.config.json"""
exitCode = oShell.Run("cmd /c " & psCmd, 0, True)
WScript.Quit exitCode
