# Gejoguessr - Influencer Guessing Game

Gejoguessr is a simple browser-based game where users are shown a picture of an influencer and guess whether they identify as "Gay" or "Straight". After guessing, the user sees aggregated statistics based on how the community has voted.

**Disclaimer:** This game is intended purely for **humorous and entertainment purposes**. It does not aim to offend, attack, or make definitive statements about the individuals depicted or any social group. Sexual orientation is a personal matter, and this game is not a tool for determining it. Please play respectfully.

## Features

* **Guessing Gameplay:** View an influencer's image and choose "Gay" or "Straight".
* **Community Stats:** See how other users voted for the same influencer, displayed as a simple bar chart.
* **Influencer Rotation:** Displays influencers randomly. Uses browser `localStorage` to track seen influencers within a session and attempts to avoid showing the same person repeatedly until the pool runs low.
* **Minimalist UI:** Clean and simple interface.
* **Multi-language Support:**
    * Polish (pl)
    * English (en)
* **Background Loading:** Pre-fetches the first influencer while the initial disclaimer is shown for a smoother start.
* **Easy Content Addition:** Designed to easily add new influencers via database entries.

## Setup

1.  **Clone Repository:**
    ```bash
    git clone https://github.com/AsmekYT/gejoguessr.git
    cd gejoguessr
    ```
2.  **Web Server:** Set up a web server (e.g., Apache, Nginx) with PHP support. Ensure the document root points to the directory containing `index.html`.
3.  **Database:**
    * Create a MySQL database and a dedicated user with privileges for that database.
    * Connect to your MySQL server (e.g., via phpMyAdmin or command line) and execute the following SQL to create the necessary table:
        ```sql
        CREATE TABLE influencers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            image_url VARCHAR(512) NOT NULL,
            votes_gay INT DEFAULT 0,
            votes_straight INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE
        );
        ```
4.  **Configuration:**
    * Create a `db_config.php` file. **IMPORTANT:** For security, place this file **outside** your web server's document root (e.g., one level above `public_html` or `htdocs`).
    * Add your database credentials to `db_config.php`:
        ```php
        <?php
        define('DB_SERVER', 'your_db_host');
        define('DB_USERNAME', 'your_db_user');
        define('DB_PASSWORD', 'your_db_password');
        define('DB_NAME', 'your_db_name');

        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);


        if ($conn->connect_error) {
            error_log("Connection failed: " . $conn->connect_error);
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }


        $conn->set_charset("utf8mb4");
        ?>
        ```
    * Verify the relative path in the `include_once` lines within `api/influencer.php` and `api/vote.php` correctly points to your `db_config.php` file. The current path is `__DIR__ . '/../../db_config.php'`, assuming `db_config.php` is two levels above the `api` directory. Adjust if your structure differs.
6.  **Add Influencers:** Populate the `influencers` table with data (see next section).
7.  **You are all set, have fun!**

## Adding New Influencers

To add a new person to the game:

1.  Upload their image to `/api/images`.
2.  Connect to your MySQL database (e.g., using phpMyAdmin).
3.  Select the `influencers` table.
4.  Insert a new row, providing:
    * `name`: The influencer's name or pseudonym.
    * `image_url`: The relative path (e.g., `images/influencer_a.png`) or absolute URL to their image.
    * Leave `votes_gay` and `votes_straight` as `0`.
    * Ensure `is_active` is set to `1` (or `TRUE`) for them to appear in the game.