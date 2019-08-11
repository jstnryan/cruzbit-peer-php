# cruzbit-peer-php
This is an example client ("peer") for the [cruzbit network](https://cruzbit.github.io) written in PHP. It is not a fully validating node, but implements specific 'one-way' functionality to sync and live track the entire blockchain, using a MariaDB (or MySQL, if desired) database for storage.

## Features At-A-Glance
* uses [ratchetphp/Pawl](https://github.com/ratchetphp/Pawl) for WebSockets functionality (which is sort of like a lightweight Ratchet)
* event driven handling of the following messages: `inv_block`, `block`, `find_common_ancestor`
* properly syncs and downloads the entire blockchain to the current tip
* handles manual reconnection gracefully, continuing sync at the point where disconnection happened
* written in the style of the [reference client](https://github.com/cruzbit/cruzbit) code (even to the level of having the same comments), so that the two can be easily compared

## Setup
cruzbit-peer-php should run on most PHP installations (actively tested on PHP 7.2), with MariaDB or MySQL (there is [one query](https://github.com/jstnryan/cruzbit-peer-php/blob/master/src/Database.php#L352) which requires MariaDB, and will need to be adjusted for other DB software).

1. Create a new database (an a user, if you like); the default is `blockchain` but can be specified in settings

   ```sql
   CREATE DATABASE blockchain
   ```

2. Add structure and populate initial data

   ```shell script
   mysql blockchain -uroot -p < blockchain.sql
   ```

3. Configure

   1. Create `settings.php`
   
      ```shell script
      cp settings.php.dist settings.php
      ```
      
   2. Edit `settings.php`
   
      ```php
      'database' => [
          'host' => 'localhost',
          'user' => 'username',
          'password' => 'password',
          'database' => 'blockchain',
      ],
      ```
 
 4. Run from command line
This script should be happy to stay running in the background. There are a multitude of ways to accomplish this, for example your favorite terminal multiplexer (if you don't have a favorite already, try [tmux](https://github.com/tmux/tmux/wiki) or [screen](https://www.gnu.org/software/screen/screen.html)).

    ```shell script
    php run.php [options]
    ```

    * Options:
      * Noise Level - There are five 'noise levels' available. Ranging from most terse to mose verbose they are; silent, quiet, normal, verbose, and debug. Normal is the default, and does not require the use of an option switch. Each level has an option (`--silent`) and a short option (`-s`). The `--noise-level` can be used to directly set the level by number, does not have a short option, and requires `N` to be a digit between 0 and 4, inclusive. 
         * Silent will not output anything to the command line, except fatal PHP errors.
         * Quiet will only output major program errors and critical warnings.
         * Normal emulates the reference client's logging level. 
         * Verbose outputs logging for many significant client operations
         * Debug will show everything, and includes a lot of low-level information, including raw data structures
        ```
        -s, --silent   
        -q, --quiet      
        -v, --verbose    
        -d, --debug
        --noise-level N
        ```
