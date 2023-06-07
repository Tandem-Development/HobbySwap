# HobbySwap

---

## Local Installation

- Clone the repo with `git clone git@github.com:Tandem-Development/HobbySwap.git`
- Make sure the latest version of Lando is installed on your machine. If it isn't already, go [here](https://lando.dev/download/).
- Spin up your environment with `lando start`
- Install Drupal and all necessary vendor files with `lando composer install`
- Acquire a database backup file and follow the steps listed below to import it.
- Finally, copy/paste `web/sites/settings.local.php` to `web/sites/default/settings.local.php`

## Importing database backups

- All database backups should reside in the `db_backups` folder at this project's root. If you don't have the folder, make it
- Lando's included `db-import` command does the job just fine.
- Simply run `lando db-import db_backups/<file>`
- There's no reason to unzip any backups. Lando handles decompression for you.

## Compiling Sass

- It's super simple! For a one-time build, run `lando build-theme`
- During development, keep the compiler running with `lando watch-theme`

## Deploying to Pantheon

- If you haven't already, add the Pantheon repository as a remote with `git remote add pantheon ssh://codeserver.dev.2ba2461a-22d0-4cf7-8c9d-8e2a2f4cc052@codeserver.dev.2ba2461a-22d0-4cf7-8c9d-8e2a2f4cc052.drush.in:2222/~/repository.git`
- Run `git checkout master && git pull origin master` to make sure your local repo is up-to-date.
- Push to pantheon with `git push pantheon master`. Provided everything goes well, Pantheon will automatically begin deployment.