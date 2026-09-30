<?php

/*
| MOVED 2026-09-27 into the sport's own folder: app/Events/Sports/BrazilianJiuJitsu/divisions/gi.php
|
| A sport's weight table is its own (CLAUDE.md → Shared stays shared, private
| stays private), so it lives beside the sport and is read through the sport's
| weightDivisions(). This file only keeps the old config key answering for
| anything that still reads it. Edit the table THERE, not here.
*/

return (static fn () => require app_path('Events/Sports/BrazilianJiuJitsu/divisions/gi.php'))();
