# GEMINI.md

## Project Overview

This is a Laravel-based web application named **lan-install.online**. Its primary purpose is to manage installation and maintenance requests for LAN networks. The application includes features for managing work brigades, tracking service requests, generating reports, and handling geographical data using Yandex.Maps.

### Key Technologies:

*   **Backend:** Laravel (PHP)
*   **Frontend:** JavaScript, Vite, Tailwind CSS, Bootstrap
*   **Database:** Postgresql (inferred from `README.md`)
*   **APIs:** Yandex.Maps

### Architecture:

The project follows a standard Laravel MVC (Model-View-Controller) architecture.

*   **Models:** Located in `app/Models/`, they interact with the database.
*   **Views:** Blade templates located in `resources/views/`, responsible for the presentation layer.
*   **Controllers:** Located in `app/Http/Controllers/`, they handle user requests and business logic.
*   **Routes:** Defined in `routes/web.php` and `routes/api.php`, mapping URLs to controller actions.
*   **Frontend Assets:** Managed by Vite and located in `resources/js/` and `resources/css/`.

## Building and Running

### Prerequisites:

*   PHP 8.2 or higher
*   Composer
*   Node.js and npm
*   Postgresql

### Setup and Execution:

1.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

2.  **Install JavaScript dependencies:**
    ```bash
    npm install
    ```

3.  **Configure environment:**
    Copy `.env.example` to `.env` and fill in your database credentials and other settings.
    ```bash
    cp .env.example .env
    ```

4.  **Generate application key:**
    ```bash
    php artisan key:generate
    ```

5.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

6.  **Seed the database (optional):**
    ```bash
    php artisan db:seed
    ```

7.  **Build frontend assets:**
    ```bash
    npm run build
    ```

8.  **Run the development server:**
    ```bash
    php artisan serve
    ```
    Alternatively, for a complete development environment including the queue listener and logs, use:
    ```bash
    composer run dev
    ```

## Development Conventions

### Testing:

*   Run the test suite using the following command:
    ```bash
    composer run test
    ```

### Code Style:

*   The project uses `laravel/pint` for PHP code style. To format the code, run:
    ```bash
    vendor/bin/pint --fix
    ```

### API:

*   API documentation is available in `docs/API.md`.
