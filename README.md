# Akane
Akaihane Channel is a french image-board project created for https://www.akane-ch.org

Installation instructions are described in french in the source code. Here is a translation of the setup.

1) Create a directory in the root folder. Paste the akane.php file and create the following folders res/ src/ and thumb/.

        SERVER_ROOT/
        |
        +-- <board name>/
            |
            +-- Akane.php
            |
            +-- res/
            |
            +-- src/
            |
            +-- thumb/


    2) Create a database ("imageboard" by default, otherwise rename it in the parameters below).

    3) Edit the parameters.

    4) Execute akane.php in a web browser.

Requierments : PHP, GD, PDO, MySQL. Nothing too specific, just run a Wamp server and it works.

Features :
- sage
- admin tools (delete, ban, unban, lock, sticky, etc...)
- tripcodes/capcodes
- inline catalog view
- Search engine links
- Duplicate files protection
- Mouse hover on >>quotes makes them pop-up
- Anti-spam cooldown
