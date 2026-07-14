# Перенос сайтов с `.10` (lan-install) на Dell/ESXi (`.3`)

Задача от заказчика (Фурса): перенести весь стек с сервера `192.168.88.10` (bare metal, Debian)
на офисный Dell PowerEdge R620 (`192.168.88.3`), который уже работает как гипервизор VMware ESXi.
Прод в эксплуатации — сначала план и тестирование, переключение трафика только в согласованное окно.

Статус: `[ ]` — ждёт, `[x]` — сделано.

---

## Доступ с мака (SSH-туннели через `.10`)

Мак **не видит** офисную сеть `192.168.88.0/24` напрямую — только через `.10` как jump host
(внешний порт 22 → `.10`, NAT-правило #2). Все туннели едут поверх SSH-соединения к `.10`.

| Куда | Команда (в отдельной вкладке терминала, держать открытой) | В браузере |
|---|---|---|
| **ESXi** (Dell/VMware `.3`) | `ssh -L 8443:192.168.88.3:443 lan-install` | `https://localhost:8443` |
| **MikroTik** (роутер `.1`) | `ssh -L 8090:192.168.88.1:80 lan-install` | `http://localhost:8090` |
| **Cockpit** на `.10` | `ssh -L 9090:192.168.88.10:9090 lan-install` | `https://localhost:9090` |
| **Реплика** `pg-replica` (`.55`) | `ssh pg-replica` | — (SSH, ProxyJump `lan-install`, user `sergey`, ключ `~/.ssh/id_rsa`) |

- Self-signed сертификаты (ESXi/Cockpit) — подтвердить исключение в браузере, это норма.
- «Address already in use» → локальный порт занят старым туннелем: взять другой (8444, 8091, …)
  или закрыть старую сессию (`lsof -i :8443` → `kill`).
- Учётки WebFig MikroTik/ESXi — у пользователя (в чат не выводим).

---

## Инвентаризация

### Dell / ESXi (`192.168.88.3`)
- Модель: **Dell PowerEdge R620**, серийный `1FLYD5J`, BIOS 1.2.6 (2012)
- **VMware ESXi 6.5.0 Update 3** (Build 17167537), DellEMC-кастомная сборка образа
  — ⚠️ версия без поддержки VMware с октября 2022 (патчей безопасности нет)
- 12×vCPU (Intel Xeon E5-2640 @2.50GHz), 95.94 ГБ RAM
- Уязвимость CVE-2018-3646 (L1TF, аппаратная, информационно)
- Не подключён к vCenter (standalone host)
- Сеть управления: vmk0 `192.168.88.3`, шлюз `192.168.88.1`, DNS `192.168.88.1` + `8.8.8.8`
- Доступ: веб-панель (vSphere Host Client) только изнутри офисной сети, SSH закрыт.
  С мака — через SSH-туннель: `ssh -L 8443:192.168.88.3:443 lan-install`, далее в браузере
  `https://localhost:8443` (self-signed сертификат — подтвердить исключение).

**Уже существующие VM (не трогаем, чужая инфраструктура):**
| VM | ОС | Занято | Назначение (предположительно) |
|---|---|---|---|
| VPN_openWRT_openConnect | Linux | 11.11 ГБ | VPN-роутер |
| 1c_haspemul.5.1.13 | Linux 32-bit | 6.11 ГБ | HASP-эмулятор лицензии 1С |
| 1c_WindowsServer (`1c-winsrv`) | Windows Server 2016+ | 222.28 ГБ | сервер 1С (бухгалтерия) |
| VPN (`vpn`) | Ubuntu | 20.11 ГБ | VPN-сервер |
| Cisco_Wifi | — | 3.55 ГБ | Wi-Fi контроллер |

**Datastores (2 независимых RAID-массива):**
| Name | RAID | Capacity | Free | Type |
|---|---|---|---|---|
| raid1-1.2tb | RAID1 (зеркало) | 1.08 ТБ | 878.51 ГБ | VMFS6 |
| **raid5-4.4tb** | RAID5 | 4.36 ТБ | **4.31 ТБ** | VMFS6 |

Существующие 5 VM живут на `raid1-1.2tb`. Новую VM решили ставить на **`raid5-4.4tb`**
(много свободного места, отдельный от существующих VM физический массив — нет конкуренции за I/O).

### `.10` (lan-install, что переносим)
Debian 12 (bookworm), 4 vCPU, 3.7 ГБ RAM, диск `/dev/md0` 1.8 ТБ (занято 433 ГБ).
Сеть — NetworkManager, интерфейс `bridge0` (мост `eno1`+`enp2s0`) по DHCP
→ IP `192.168.88.10` почти наверняка закреплён на MikroTik статической DHCP-резервацией по MAC.

**Сайты/сервисы (nginx vhosts):**
| Домен | Backend | Что это |
|---|---|---|
| lan-install.online, server.lan-install.online | PHP 8.2-FPM, `/var/www/lan-install/public` | основной Laravel-проект |
| erp.lan-install.online | Node, proxy `:5005` | ERP (статика в `dist`) |
| findoc.lan-install.online | Node, proxy `:3002` | — |
| game.lan-install.online | Node, proxy `:3050` | — |
| lan-install.ru | Node, proxy `:3000` | — |
| stock.lan-install.online | Node, proxy `:5001` | stock-flow WMS |
| storage.lan-install.online | Node, proxy `:5002` | — |
| zabbix.lan-install.online | PHP-FPM (`zabbix.sock`) | мониторинг — **переезжает на отдельную VM**, см. ниже |

**PostgreSQL 16:** кластер `main` (5432, БД `lan_install` 606 МБ, `server_lan_install` 542 МБ,
`zabbix` — Zabbix server реально пишет в Postgres, ~25 активных соединений, MySQL/MariaDB на
сервере тоже запущен, но Zabbix его не использует) + `replica` (5434, репликация).
Кластер PG15 — отключён/не используется (`down`).

**Прочее:** cron (планировщик Laravel через `sudo -u www-data schedule:run`, см. память
`prod-scheduler-www-data`), supervisor.

**Node-сервисы на `.10` (важно для Фазы 2 — миграция приложений):** запускаются через **systemd**
(НЕ supervisor/pm2), под пользователем **www-data**. Node на `.10` разбросан по 3 местам:
`/usr/bin/node` (системный), `/opt/node-20/bin/node` (ручная установка), `/root/.nvm/.../v20.19.5`.
На новой VM решили ставить ОДИН системный Node 20 (NodeSource) → чистый `/usr/bin/node`+`npm`.
Найденные systemd-юниты (неполный список — есть ещё процессы на 5001/5002/3050 без явных юнитов):
- `erp.service` → `/usr/bin/npm run start:dev`, WD `/var/www/erp.lan-install.online/backend` (порт 5005)
- `findoc-api.service` → `/usr/bin/node .../apps/api/dist/src/index.js` (порт 3002)
- `lan-install-ru.service` → `/opt/node-20/bin/node .../next start` (порт 3000)
- ??? stock-flow (5001), storage (5002), game (3050) — механизм запуска уточнить при переносе.

### Карта NAT на MikroTik (`192.168.88.1`, RouterOS 6.49.20) — снято 2026-07-10

WAN-порт (внешний): **`ether24`**, внешний IP **`176.99.191.178`**.
Выход в интернет: правило `chain=srcnat action=masquerade out-interface=ether24` (все внутренние
устройства выходят наружу через один внешний IP).

Проброс портов внутрь (`chain=dstnat action=dst-nat`, dst-address=176.99.191.178, если не указано иное):

| # | Внешний порт | Протокол | → to-address | Что это |
|---|---|---|---|---|
| 1 | 80 | tcp | **192.168.88.10** | сайты (http) |
| 2 | 22 | tcp | **192.168.88.10** | SSH / **jump host** |
| 3 | 443 | tcp | **192.168.88.10** | сайты (https) |
| 4 | 9090 | tcp | **192.168.88.10** | Cockpit |
| 10 | 443 | tcp | **192.168.88.10** | hairpin (in-interface=bridge1-laninstall) |
| 11 | 80 | tcp | **192.168.88.10** | hairpin (in-interface=bridge1-laninstall) |
| 5 | 5001 | tcp | 192.168.88.18 | (камера/устройство .18) |
| 12 | 21 | tcp | 192.168.88.18 | FTP |
| 6 | 8000 | tcp | 192.168.88.64 | — |
| 7 | 3389 | tcp | 192.168.88.64 | RDP (Windows, тяжёлый трафик) |
| 18 | 5173 | tcp | 192.168.88.64 | — |
| 8 | 5443 | tcp | 192.168.88.4 | (in-interface=ether24) |
| 9 | 5443 | udp | 192.168.88.4 | (in-interface=ether24) |
| 13 | 2096 | tcp | 192.168.88.7 | — |
| 14 | 3257 | tcp | 192.168.88.7 | — |
| 15 | 25555 | tcp | 192.168.88.7 | — |
| 16 | 28173 | tcp | 192.168.88.7 | — |
| 17 | 42772 | tcp | 192.168.88.7 | — |
| 19 | 22222 | tcp | 192.168.88.7 | — |

**Для Фазы 4 (cutover):** на новую VM перенаправляем правила, ведущие на `.10` — прежде всего
**#1 (80)** и **#3 (443)** (все сайты), опционально **#2 (22)**, **#4 (9090)**, **#10/#11** (hairpin).
Меняется только `to-addresses` (192.168.88.10 → IP новой VM). Правила на `.18/.64/.4/.7` — НЕ трогаем.
Откат — вернуть `to-addresses` обратно.

**Замечания по безопасности (не срочно):** Cockpit (9090) и SSH (22) на `.10` открыты в интернет
напрямую — точки для брутфорса, стоит подумать об ограничении по source-IP / выносе за VPN.

**Про jump host:** доступ с мака к офисной сети (ESXi, MikroTik, реплика) идёт ТОЛЬКО через `.10`
(внешний 22 → правило #2). Все туннели (`-L …`, ProxyJump) едут поверх SSH к `.10`. При переносе
`.10` остаётся включённым (сейчас primary, потом реплика) — дверь в сеть сохраняется.

### Сеть офиса (192.168.88.0/24)
- Шлюз/маршрутизатор по умолчанию: **MikroTik `192.168.88.1`** — почти наверняка тут настроен
  NAT/port-forward, который принимает `176.99.191.178:80/443` (внешний IP) и шлёт на `.10`.
  **Доступ пока не проверяли** (нужны креды или помощь офисного инженера/Алексея).
- Второй MikroTik: `192.168.88.2` — роль не выяснена.
- `192.168.88.18` — **не сервер**, это IP-камера/NVR (заголовок `Server: gSOAP/2.8`, ONVIF).
  Ранее данный сетевым инженером Алексеем доступ `ssh -p 2222 sysadmin@192.168.88.18` сейчас
  не отвечает (порт закрыт) — либо адрес переехал к камере по DHCP, либо тот сервис выключен.
  Не имеет отношения к `.10`.
- Внешний IP `.10`: `176.99.191.178`.

---

## Принятые решения
- **Одна VM** на новый стек (как сейчас на `.10`), не дробим по ролям.
- Datastore: **`raid5-4.4tb`**.
- Финальное переключение трафика — **в короткое ночное окно** (даунтайм на минуты), не blue-green.
- IP новой VM — получить по DHCP при установке, затем закрепить статической резервацией на MikroTik
  (тот же паттерн, что и у `.10`), а не прописывать вручную.
- **Zabbix — отдельная VM**, не в общем стеке. Причина: мониторинг не должен падать вместе с тем,
  что он мониторит (если основная VM ляжет — Zabbix на ней тоже ляжет и не пришлёт алерт именно
  тогда, когда это важнее всего). Ресурсы на Dell позволяют — не про нагрузку, а про надёжность.
- При переносе Zabbix — поставить **TimescaleDB** (расширение Postgres, официально поддерживается
  Zabbix для `history`/`trends`): автосжатие/ретеншн старых метрик, быстрее запросы по мере роста
  данных. Ставить сразу на новом окружении — дешевле, чем переделывать потом на живых данных.

## Физическая репликация PostgreSQL (main-кластер)

**Решение ОБНОВЛЕНО 2026-07-10 (пересмотрено по ходу работы):**
Изначально планировали временный шаг `.10` (primary) → `.55` (replica), а после общего переноса
сайтов — менять роли местами. Отказались от этого: незачем настраивать репликацию дважды.
**Новая последовательность:**
1. **Сначала строим `lan-install-new`** (Фаза 0 ниже — VM под сайты на Dell, `raid5-4.4tb`).
   VM `pg-replica` (`.55`) уже создана и ждёт (Debian 12, IP `192.168.88.55` закреплён на MikroTik,
   SSH настроен) — PostgreSQL на неё **пока не ставим**.
2. **Когда `lan-install-new` готова и на неё переехал primary** (Фаза 2 основного плана) —
   настраиваем потоковую (streaming) репликацию сразу в финальном виде: **`lan-install-new`
   (primary, Dell) → `.55` (replica, тоже Dell)**.

⚠️ Разделения по физическому железу между primary и репликой в этой схеме **больше нет** —
обе VM на одном Dell. Это принятый компромисс ради простоты (не настраивать репликацию дважды).
Если понадобится защита от отказа Dell — отдельный вопрос на будущее (напр. `.10`, оставшийся
свободным bare-metal, как ещё одна копия).

**Судьба `.10` после переноса сайтов:** становится **чистым jump host + Zabbix** (см. ниже —
Zabbix решили вообще не переносить, он остаётся на `.10` навсегда). Роль реплики БД `.10` в
пересмотренном плане не играет.

**Текущий статус: ставим на паузу шаги «Этап 1» ниже (они больше не актуальны в прежнем виде) —
идём к Фазе 0 основного плана (создание `lan-install-new`).**

### ~~Этап 1~~ (УСТАРЕЛО — см. решение выше, идём сразу к Фазе 0 основного плана)
- [ ] Создать VM на `raid5-4.4tb`: 2 vCPU, 4 ГБ RAM, диск ~150 ГБ thin (под data dir + WAL,
      сейчас `lan_install`+`server_lan_install` ≈ 1.2 ГБ, запас на рост)
- [ ] Debian 12 + PostgreSQL 16 (та же версия, что на `.10`)
- [ ] На `.10` (primary) — **показать точный diff перед применением**, прод в эксплуатации:
      - создать роль репликации (`REPLICATION LOGIN`)
      - `pg_hba.conf` — разрешить репликацию с IP новой VM
      - `postgresql.conf` — проверить `wal_level=replica`, `max_wal_senders`, настроить replication slot
      - `reload`/`restart` Postgres по необходимости
- [ ] На новой VM — `pg_basebackup` с `.10`, `standby.signal` + `primary_conninfo`, старт реплики
- [ ] Проверить: `pg_stat_replication` на primary, лаг репликации на реплике, реальный failover
      сценарий (не переключать прод, просто убедиться, что реплика применяет WAL)
- [x] **IP реплики закреплён на MikroTik** (2026-07-10): `192.168.88.55` ← MAC `00:0C:29:24:46:52`,
      статическая резервация (Make Static), комментарий `pg-replica PostgreSQL`.
      VM создана `pg-replica`, hostname `pg-replica`, пользователь `sergey` (root по SSH запрещён),
      вход с мака: `ssh pg-replica` (ProxyJump через lan-install, ключ `~/.ssh/id_rsa`).

### Этап 2 — Настройка репликации (после того как `lan-install-new` готова и primary переехал)
- [ ] Primary переезжает на `lan-install-new` вместе с приложением (Фаза 2 основного плана)
- [ ] На `lan-install-new` — установить PostgreSQL 16.10, настроить как primary (роль репликации,
      `pg_hba.conf`, `postgresql.conf`) — показать diff перед применением
- [ ] На `.55` — установить PostgreSQL 16.10, `pg_basebackup` с `lan-install-new`,
      `standby.signal` + `primary_conninfo`, старт реплики
- [ ] Проверить: `pg_stat_replication` на primary, лаг на реплике
- [ ] `.10` **Zabbix НЕ переносим** — остаётся работать там же, где сейчас (см. раздел Zabbix ниже) —
      это и есть его настоящая защита (другое физическое железо от Dell/primary)

---

## Открытые вопросы
- [ ] Доступ к MikroTik `192.168.88.1` (read-only: `/ip firewall nat print`, `/ip dhcp-server lease print`) —
      узнать текущее NAT-правило `176.99.191.178`→`.10` и DHCP-пул/резервации.
- [ ] Точный список systemd/PM2 юнитов для Node-сервисов на `.10` (для 1:1 повтора на новой VM).
- [ ] Нужна ли реально репликация PG (`replica`, 5434) на новой VM, или это можно не переносить.

---

## План (по фазам)

### Фаза 0 — Создание VM на Dell (можно начинать уже сейчас, трафик не трогает)

- [x] **Готово (2026-07-11):** VM `lan-install-new` создана и установлена — Debian 12, 4 vCPU,
      8 ГБ RAM, 600 ГБ (Thick provisioned, datastore `raid5-4.4tb`), SCSI LSI Logic Parallel.
      IP `192.168.88.67` ← MAC `00:0C:29:12:E0:7D`, закреплён статической резервацией на MikroTik.
      Пользователь `sergey` (root по SSH запрещён), вход с мака: `ssh lan-install-new`
      (ProxyJump через lan-install, ключ `~/.ssh/id_rsa`).

- [ ] Скачать Debian 12 netinst ISO (amd64) с debian.org
- [ ] Storage → `raid5-4.4tb` → Datastore browser → создать папку `ISO` → Upload iso
- [ ] Virtual Machines → Create/Register VM → New VM:
      Guest OS = Debian GNU/Linux 12 (64-bit), Datastore = `raid5-4.4tb`,
      CPU = 4, RAM = 8192 MB, Disk = 600 GB thin provisioned,
      Network = `VM Network`, CD/DVD = загруженный ISO (Connect at power on)
- [ ] Power on → Console → установка Debian:
      hostname `lan-install-new`, root-пароль (сохранить у себя, не в чат),
      partitioning — guided, весь диск, один раздел,
      software selection — только SSH server + standard system utilities
      (nginx/PHP/Node/Postgres ставим вручную отдельно, версии контролируем сами)
- [ ] После установки: отключить/поменять на «Client Device» CD/DVD, проверить `ip a`
- [ ] Закрепить полученный DHCP-адрес статической резервацией на MikroTik (см. открытый вопрос выше)

### Zabbix — ОБНОВЛЕНО 2026-07-10: никуда не переносим, остаётся на `.10`

> Первое решение было «отдельная VM под Zabbix» (для надёжности — не падать вместе с тем, что
> мониторит), затем «переиспользовать `.55` после Этапа 2». Пересмотрели ещё раз: `.10` (ELSKY) —
> физически ДРУГОЕ железо, чем Dell (где будут жить и `lan-install-new`, и `.55`). Если Zabbix
> держать на `.10` — при отказе Dell (primary) Zabbix жив и пришлёт алерт; это и есть настоящая
> защита, ради которой всё затевалось. Перенос на `.55` эту защиту как раз ломает (`.55` на том
> же Dell, что и primary). Плюс — Zabbix там уже стоит и работает, миграция не нужна вообще.

**Действие: НЕ ТРЕБУЕТСЯ.** Zabbix остаётся на `.10` в текущем виде (Postgres backend, порт 5432,
БД `zabbix`) и после того, как сайты переедут на `lan-install-new`. `.10` после переноса сайтов
выполняет две роли: **jump host** (SSH-трамплин в офисную сеть) + **Zabbix**.
TimescaleDB — можно поставить и на существующую БД `zabbix` на `.10` отдельным шагом, если
`history`/`trends` станут заметно расти — не срочно, не привязано к переносу сайтов.

### Фаза 1 — Базовый стек
- [x] **fail2ban (2026-07-11)** на `lan-install-new`: `/etc/fail2ban/jail.local` с `backend = systemd`
      (на minimal Debian 12 нет rsyslog/auth.log → читаем journald напрямую), jail `[sshd]`,
      maxretry=5 / findtime=10m / bantime=1h, `ignoreip` включает `192.168.88.0/24` (не забанить себя).
      NB: тот же приём (`backend = systemd`) понадобится и для будущих jail при выносе в интернет.
- [x] **sudo** доставлен (в minimal-установку не входит — ставили вручную), `sergey` добавлен в группу `sudo`.
- [x] **Базовый стек установлен (2026-07-11):**
      - nginx 1.22.1 (Debian repo) ✓ = .10
      - PHP 8.2.32 + расширения (Debian repo, НЕ sury) — на .10 сейчас 8.2.31 (новее security-патч, совместимо)
      - PostgreSQL 16.14 (PGDG repo) — на .10 16.10 (новее патч 16.x; на `.55` поставить ТУ ЖЕ 16.14)
      - Node v20.20.2 системно (NodeSource, `/usr/bin/node`) — вместо nvm-зоопарка .10; npm 10.8.2 = .10
      - Composer 2.10.2, Certbot 2.1.0, supervisor 4.2.5
      - Все сервисы active. Redis не ставили (на .10 нет).

### Фаза 2 — Перенос данных (заранее, без даунтайма)

**rsync-канал `.10 → .67`:** ключ `/root/.ssh/migrate_to_67` на `.10` → root authorized_keys на `.67`
(временный, убрать после миграции). rsync/pg_dump — только ЧИТАЮТ `.10` (прод в безопасности).

**Фаза 2a — основное приложение lan-install (2026-07-12): [почти готово]**
- [x] Код+vendor+node_modules+`.env` rsync `.10:/var/www/lan-install/ → .67` (владелец www-data сохранён,
      root→root). Исключены: `storage/app/public` (переносится отдельно), `storage/app/temp` (78ГБ кэш архивов — НЕ переносим, регенерируется).
- [x] БД: `pg_dumpall --roles-only` + `pg_dump -Fc lan_install/server_lan_install` на `.10` →
      restore на `.67`. Роли (postgres/laravel_user/erp_user/findoc_user/tool_user/replicator/zabbix)
      с хешами паролей → `.env` работает без правок. Размеры совпали (605/543 МБ).
- [x] Приложение подключается к БД (`migrate:status` ok), APP_KEY на месте.
- [x] `/etc/letsencrypt` + `/etc/nginx` скопированы; убрали `conf.d/zabbix.conf` (симлинк на отсутствующий
      `/etc/zabbix/`, Zabbix остаётся на `.10`) и zabbix-vhost. `nginx -t` ok, reload ok.
- [x] Проверка: `lan-install.online`/`server.lan-install.online` → 302→/login, `/login` → 200 «Вход»,
      ошибок в laravel.log нет. symlink `public/storage` на месте.
- [x] rsync `storage/app/public` (170ГБ фото) — ЗАВЕРШЁН (размеры .10 и .67 совпали: 170G=170G).
- [x] Браузерный тест с реальным логином (Safari через туннель+hosts): вход, заявки, планирование,
      адреса, отчёты — всё 200, данные корректны. Заказчик подтвердил «вроде норм».
- [ ] crontab (планировщик Laravel), systemd Node-юниты — Фаза 2b (остальные сайты).

**Остальные сайты (Фаза 2b) — по одному:** erp, findoc, game, lan-install.ru, storage,
tool-system. Node-сервисы через systemd (www-data). Порядок — после основного.

> ⚠️ ВАЖНО про склады: их ДВА — `stock.lan-install.online` (stock-flow.service, :5001) и
> `storage.lan-install.online` (storage-flow.service, :5002). **ОСНОВНОЙ рабочий — `storage`**
> (все на нём работают, заказчик подтвердил). `stock` НЕ переносим (остаётся на `.10`).
> Обе БД-URL указывают на общую базу `stock_wms`. (Память `wms-integration-architecture` про
> «склад = stock-flow» — неточна, обновить.)

**storage.lan-install.online — ГОТОВО (2026-07-12):**
- [x] rsync кода (без node_modules/dist), `pg_dump stock_wms` (14МБ, 22 табл) → restore на `.67`.
- [x] `.env`: бэкап прод-значений → `.env.prod-backup`; на время теста Telegram переключён на
      личный бот пользователя (@FlowboxNotifyBot, chat 1020570278, topic очищен) — при cutover вернуть.
- [x] `npm install` (289 пакетов) + `npm run build` (vite→dist), владелец www-data.
- [x] `storage-flow.service` (systemd, www-data, `npx tsx server/index.ts`) скопирован, enable --now,
      active, слушает :5002.
- [x] Проверка: `storage.lan-install.online` → 200 «Склад WMS», backend отвечает, ошибок в логах нет.
- [x] Браузерный тест (Safari): вход, номенклатура/расходники/ТС/склады/история/юзеры — всё 200,
      данные из stock_wms корректны. NB: у vhost складов ОТДЕЛЬНЫЕ логи (`storage_access.log`), не общий.
- [ ] Проверка Telegram-уведомления в @FlowboxNotifyBot (сделать действие, шлющее уведомление).

**lan-install.ru — ГОТОВО (2026-07-12):** простой сайт, БЕЗ БД. Next.js 16.2.2, systemd `lan-install-ru.service`.
- [x] rsync кода (без node_modules/.next). Владелец после rsync был `UNKNOWN:staff` (несовпадение UID
      .10↔.67) — пофиксили `chown -R www-data:www-data`. **Учесть для остальных сайтов Фазы 2b —
      всегда проверять владельца после rsync, не только для .ru.**
- [x] Security-находка (не блокирует, отдельная задача): в `lan-install-interactive/src/config.ts`
      TG_BOT_TOKEN/TG_CHAT_ID захардкожены в исходнике (не через .env), используются в клиентском
      компоненте `Contact.tsx` — токен вероятно попадает в браузерный JS-бандл. `.env`/`.env.local`
      TELEGRAM_MOUNTING_PANELS_* — не используются нигде в коде (мёртвый конфиг).
- [x] Для теста подменили в config.ts на @FlowboxNotifyBot (бэкап оригинала → `config.ts.prod-backup`,
      вернуть перед cutover).
- [x] Юнит systemd правили: оригинал ссылался на `/opt/node-20/bin/node` (легаси-путь с .10) →
      поправили на `/usr/bin/node` (единый системный Node на .67).
- [x] `npm install` (422 пакета) + `npm run build` — успешно. Сервис active, порт 3000.
- [x] Проверка: `/`, `/calculator`, `/contact`, `/portfolio` — все 200.

**tool-system — НЕ ПЕРЕНОСИМ (решение заказчика, 2026-07-12).** Django/Gunicorn (порт 3001), Django-модуль
буквально называется `inventory` + есть папка `storage` — похоже на СТАРУЮ версию склада до переписывания
на Node (нынешний storage.lan-install.online). Nginx-конфиг `/etc/nginx/sites-available/tool-system`
отключён (нет в sites-enabled), внутри ошибочно `server_name storage.lan-install.online` (чужой домен,
видимо остался с тех времён, когда tool-system и был storage). Сервис на `.10` формально ещё `active`,
но без публичного домена. БД `tool_system` (10МБ) — тоже не переносим.

**erp.lan-install.online — ГОТОВО (2026-07-13):** «HR Finance Hub». Frontend: React+Vite (root,
PWA/service worker). Backend: NestJS (`backend/`), systemd `erp.service`, `npm run start:dev`
(nest --watch, так же как на .10 — не production-режим, но повторили точно как в проде).
- [x] rsync кода (без node_modules/dist), владелец www-data сохранился корректно на этот раз.
- [x] `pg_dump erp_systems` (9095 kB, совпало с .10) → restore на `.67`.
- [x] `npm install` backend — ок. Frontend упал на ERESOLVE (родной конфликт `.10`: `eslint@10` vs
      `eslint-plugin-react-hooks@5.2.0` peer — dev-only, не влияет на рантайм) → поставили с
      `--legacy-peer-deps`, стандартное решение для такого конфликта.
- [x] `npm run build` — оба слоя (vite dist + nest build) успешно.
- [x] Юнит скопирован как есть (уже использовал системный `/usr/bin/npm`, правок не потребовалось).
- [x] Логи подтвердили: `✓ Connected to erp_systems database`, `✓ Connected to lan_install database
      (read-only)`, роуты замаплены, сервер поднят на :5005.
- [x] Проверка: `/` → 200 «HR Finance Hub», `/api/shift-types` → 401 (правильно, JWT-защита).

**findoc.lan-install.online — ГОТОВО (2026-07-13):** «FinDoc — Управленческий учёт» (бухгалтерия).
pnpm-монорепо (apps/api + apps/web + packages/shared), systemd `findoc-api.service`.
- [x] rsync кода (без node_modules/dist/.next, без старых `html.backup-*`/`html-backup-*` — мусор
      от прошлых ручных деплоев), владелец www-data.
- [x] `pg_dump findoc` (~10МБ) → restore на `.67`.
- [x] `pnpm 10.33.0` установлен глобально (та же версия, что на .10, через nvm там).
- [x] `pnpm install` в корне монорепо — подтянул api+web+shared разом. `bcrypt` (нативный модуль)
      собрался сам. **puppeteer скачал headless Chrome** — API генерирует PDF-документы; если при
      тесте генерация PDF упадёт — проверить системные зависимости Chrome (libnss3, libatk и т.п.,
      частая проблема на minimal Debian).
- [x] Build api (`tsc`) + build web (`vite`) — оба ок. Деплой web: **вручную** `cp dist/* → html/`
      (на .10 деплой-скрипта нет, тоже руками копируют — повторили тот же процесс).
- [x] Юнит скопирован как есть (уже `/usr/bin/node`). Сервис active, порт 3002.
- [x] Проверка: `/` → 200 «FinDoc — Управленческий учёт», `/api/auth/me` → 401 (правильно, защищено).
- [ ] Не проверено: генерация PDF-документов (puppeteer) — сделать при следующем тесте.

**game.lan-install.online — ГОТОВО (2026-07-13):** «Khvylka 3.0», статический React-билд без БД,
раздаётся через `npx serve -s . -l 3050`, systemd `game-lan-install.service`.
- [x] rsync кода (552КБ, без .git), владелец www-data.
- [x] **Грабли:** `www-data`'s HOME по умолчанию `/var/www` (юнит не задаёт HOME явно) → npx пытался
      писать кэш в `/var/www/.npm`, а тот не существовал/был недоступен → EACCES. Создали
      `/var/www/.npm` с владельцем www-data — заработало. **Учесть на будущее** для любых юнитов
      с `User=www-data` без явного `Environment=HOME=...`, которые используют npx/npm кэш.
- [x] Юнит скопирован как есть, сервис active, порт 3050 («Accepting connections»).
- [x] Проверка: `/` → 200 «Khvylka 3.0».

## ИТОГ Фазы 2 (перенос сайтов) — ВСЕ ГОТОВО (2026-07-13)
Перенесены и протестированы: lan-install.online (+server.), storage.lan-install.online, lan-install.ru,
erp.lan-install.online, findoc.lan-install.online, game.lan-install.online.
Осознанно НЕ перенесены: tool-system (мёртвый legacy-код), stock.lan-install.online (заказчик решил
не переносить — storage главный склад).
Осталось до полного переезда: репликация БД (`.55`), финальный cutover (NAT на MikroTik, только по
команде), возврат прод-значений в подменённые для теста `.env`/`config.ts` (Telegram-токены).

### Фаза 3 — Тестирование (без переключения трафика)
- [ ] Все сайты доступны изнутри сети/по SSH-туннелю
- [ ] Закрытие заявки → Telegram-уведомление доходит
- [ ] WMS API (X-API-Key) работает
- [ ] Zabbix, остальные Node-сервисы

**Подготовка к cutover — relay-прокладки на `.67` (2026-07-13, СДЕЛАНО):**
> Важная находка: NAT работает на уровне IP:порт (не домена) — переключение 80/443 на `.67`
> перенесёт ВЕСЬ трафик, включая `stock.lan-install.online` и `zabbix.lan-install.online`,
> которых на `.67` нет (сознательно не переносили). Без этого шага они бы отвалились после cutover.
- [x] На `.67` созданы 2 nginx-конфига-прокладки (`stock.lan-install.online`, `zabbix.lan-install.online`),
      прозрачно проксирующие `https://192.168.88.10` с сохранением Host — реальная раздача
      по-прежнему на `.10`, `.67` просто транзитом пропускает. Сертификаты уже были на `.67`
      (bulk-копия `/etc/letsencrypt` из Фазы 2a). Оба протестированы, стабильные 200.

### Фаза 4 — Cutover (ночное окно)
- [ ] Финальный дельта-rsync + свежий `pg_dump`
- [ ] Переключение NAT/port-forward на MikroTik (`176.99.191.178` → новая VM) —
      **показать точный diff правила перед применением**
- [ ] Проверка всех доменов
- [ ] `.10` оставить работать несколько дней как мгновенный откат (просто вернуть NAT-правило)

---

*Файл создан 2026-07-08. Обновлять статусы чеклистов и открытые вопросы по ходу работы —
это единственный канон плана переноса, дублировать план в памяти агента не нужно.*
