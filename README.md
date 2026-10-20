[![Tests](https://github.com/EnzymeFunctionInitiative/efi-web/actions/workflows/tests.yaml/badge.svg)](https://github.com/EnzymeFunctionInitiative/efi-web/actions/workflows/tests.yaml)

# Enzyme Function Initiative - Web Interface

This repository hosts the **Web Interface** for accessing tools provided by
the [Enzyme Function Initiative (EFI)](https://efi.igb.illinois.edu).

The project reimplements the existing EFI Web Interface ([efi-web](https://github.com/EnzymeFunctionInitiative/efi-web))
using the Symfony framework.

## Prerequisites

To set up and run the EFI Web Interface, ensure the following requirements are met:

- PHP version **8.2** or later
- PHP extensions for:
    - XML
    - Database connections for SQLite (MySQL optional)
    - intl
- SQLite
- MySQL (optional)
- **[Composer](https://getcomposer.org/download/)**: A dependency manager for PHP. (See **Note 1**
  if it is not possible to install Composer.

## Quick Start

Clone the EFI Web repository:

    git clone https://github.com/EnzymeFunctionInitiative/efi-web.git


## Configure Installation

Configure the environment and installation files.

### Configure `.env`

Copy the example file `.env.example` to `.env`.  Edit the file and ensure that the `APP_ENV`
variable is set to `dev` (e.g. `APP_ENV=dev`).  Create a `.env.local` file and insert the
following variables:

    APP_SECRET='...'
    DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
    RESULTS_DIRECTORY="%kernel.project_dir%/var/results"
    DEFAULT_DATABASE_VERSION=2024.1
    MAILER_DSN=sendmail://default
    GND_DATABASE_USER=''
    GND_DATABASE_PASSWORD=''
    GND_DATABASE_DSN=''

Ensure `APP_SECRET` is set to a secure random string.  `openssl rand -hex 32` can be used to
generate the appropriate value.  Update `MAILER_DSN` as appropriate to match the system
configuration. 

### Configure `.env.test`

Copy the example file `.env.test.example` to `.env.test`.  Edit the file and insert the following
values:

    KERNEL_CLASS='App\Kernel'
    APP_SECRET='...'
    DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
    RESULTS_DIRECTORY="%kernel.project_dir%/var/test_results"
    DEFAULT_DATABASE_VERSION=2024.1
    MAILER_DSN=sendmail://default
    GND_DATABASE_USER=''
    GND_DATABASE_PASSWORD=''
    GND_DATABASE_DSN=''

Ensure `APP_SECRET` is set to a secure random string, and `openssl rand -hex 32` can be used to
generate the appropriate value.  Update `MAILER_DSN` as appropriate to match the system
configuration. 

### Configure `config/instance.yaml`

Copy `config/instance.yaml.example` to `config/instance.yaml`.  It is not necessary to edit the
values for a quick-start installation.  In order to set the time zone for displaying date/times,
set the `app.timezone` value to one of the time zones listed at
(https://www.php.net/manual/en/timezones.php)[https://www.php.net/manual/en/timezones.php].  The
locale for the timezone can be configured in the same file by setting the `app.default_locale`
to a valid value (defaults to the system locale, but can be overridden with values such as
`en-SG`).

## Installation

Install Symfony and dependencies (from the root directory of the repository):

    composer install

## Run Tests

    php bin/phpunit tests

## Initialize Database and Create Super Admin User

The database needs to be initialized by running the following steps:

    php bin/console doctrine:schema:create

    php bin/console app:create-superadmin --email <EMAIL> --password <PASSWORD> --first-name <FIRST> --last-name <LAST>

Replace `<EMAIL>`, `<PASSWORD>`, `<FIRST>`, and `<LAST>` with appropriate values.  Once a super
admin user is created, that account can be used to add manage users and organizations through the
website user interface.

## Configure Web Server

The Symfony CLI provides a lightweight web server that can be used for development purposes on a
local system.  This project does not rely on the Symfony CLI, so the built-in PHP server can be
used instead:

    php -S localhost:8080 -t public/

### Apache on Linux Configuration

An example configuration for the Symfony site on Apache is as follows.  The path for `DocumentRoot`
and `Directory` should be substituted for the path to the site installation directory.  (The
example below assumes the site was installed at `/var/www/efi-web`.)

    DocumentRoot /var/www/efi-web/public
    <Directory /var/www/efi-web/public>
        AllowOverride None
        Require all granted

        <IfModule mod_rewrite.c>
            RewriteEngine On

            # If the requested filename is a real file serve it
            RewriteCond %{REQUEST_FILENAME} -f
            RewriteRule .? - [L]

            # Otherwise, pass it to index.php
            RewriteRule ^(.*)$ index.php [QSA,L]
        </IfModule>
        FallbackResource /index.php
    </Directory>

## Loading Example Datasets

A complete collection of example job results is located at
(https://efi.igb.illinois.edu/downloads/sample_data/efi_web_sample_jobs.tar.gz)[https://efi.igb.illinois.edu/downloads/sample_data/efi_web_sample_jobs.tar.gz].
After downloading this file and uncompressing it, ensure that the `EXAMPLE_DATA_PATH` is set to
the path of the dataset.  The following command can then be run to import the dataset into the
database:

    php bin/console app:import-example-data --email user@example.com --job-status <STATUS>

Replace `<STATUS>` with one of `new`, `finished`, `failed`, `running`, or `archived`.  The email
address must correspond to the email address of a user that already exists in the database.

## Install BLAST command line utilities

The BLAST command line utilities, in particular `fastacmd` from BLAST legacy v2.2.26, are required
for providing FASTA downloads from the sunburst viewer.  These can be installed by running the
`bin/install_blast.sh` script.  The `config/instance.yaml` file needs to be updated to save the
install directory path.

## Email Setup

The website provides the capability for sending email to the user when job status changes.  This
is enabled by installing two separate cron jobs, one for preparing the messages, and the second
task for running the Symfony messenger.  For example, assuming the scripts are installed at
`/var/www/efi-web`:

    */5 * * * * cd /var/www/efi-web/efi-web && /usr/bin/php bin/console app:notify-job-status-changes --statuses=running,finished,failed --limit=200 --env=dev >> var/log/dev-status-email.log 2>&1
    * * * * * cd /var/www/efi-web/efi-web && /usr/bin/php bin/console messenger:consume --limit=100 --time-limit=30 --env=dev >> var/log/dev-messenger.log 2>&1

Note that the `--env=` flag needs to reflect the mode that the site is operating under (as set
in `.env`).

## Notes

### Note 1

If **Composer** is not installed and cannot be installed, the latest version can be downloaded
to the root directory of the site by running the `bin/get_composer.sh` script.  This will create
an executeable PHP archive `composer.phar` in the current directory.  (While the commands in this
guide use the `composer` command, an alias can be created by executing
`alias composer="php composer.phar"`.)

## License

This project is licensed under the [GPL 3.0 License](LICENSE).
