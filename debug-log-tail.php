<?php
/*
Plugin Name: Debug.log Tail
Description: A peek at the tail of the debug.log
Version: 0.1
Author: Katherine Semel
*/

if ( ! class_exists( 'DebugLogTail' ) ) {
    class DebugLogTail {

        function DebugLogTail() {
            // Add to the dashboard
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );

            // Add to the network dashboard
            if ( is_multisite() ) {
                add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
                add_filter( 'debug_log_tail_parent_slug', array( $this, 'admin_menu_slug_for_multisite' ), 1, 2 );
            }
        }

        function admin_menu() {
            $parent_slug = apply_filters( 'debug_log_tail_parent_slug', 'tools.php', is_network_admin() );

            if ( empty( $GLOBALS['admin_page_hooks'][ $parent_slug ] ) ) {
                // Add to the Tools menu instead if the given menu does not exist
                $parent_slug = 'tools.php';
            }

            $submenu_page = add_submenu_page(
                $parent_slug,
                'View Debug.log',
                'View Debug.log',
                'manage_options',
                'view-debugger',
                array( $this, 'show_debug_log' )
            );
        }

        function admin_menu_slug_for_multisite( $slug, $is_network_admin ) {
            // Override only if this is still the default
            if ( $slug === 'tools.php' && $is_network_admin ) {
                $slug = 'settings.php';
            }
            return $slug;
        }

        function show_debug_log() {
            // Open the file and read the last lines
            $debug_log = ini_get( 'error_log' );

            $line_count = apply_filters( 'debug_log_tail_line_count', 100 );
            $result = self::tail_file( $debug_log, $line_count );

            if ( $result === false ) {
                $result = 'Debug.log file not found at "' . $line_count . '"';
            }

            // Do some highlighting
            $result = self::highlight_log( $result );

            ?>
            <div class="wrap">
                <h2><?php echo get_admin_page_title() ?></h2>
                <p>Reading last <strong><?php echo strval( $line_count ) ?></strong> lines from <strong>"<?php echo strval( $debug_log ); ?>"</strong></p>
                <style type="text/css">
                    .debuglog {
                        white-space: pre-wrap;
                    }
                    .debuglog .error {
                        color: #C00;
                    }
                    .debuglog .Warning,
                    .debuglog .Notice {
                        color: #FC0;
                    }
                </style>
                <pre class="debuglog"><?php echo $result; ?></pre>
            </div>
            <?php
        }

        /*
            Read X lines using a dynamic buffer (more efficient for all file sizes)
            @author Lorenzo Stanco
            @url https://gist.github.com/lorenzos/1711e81a9162320fde20
        */
        function tail_file( $filepath, $lines = 1, $adaptive = true ) {

            // Open file
            $handle = @fopen( $filepath, "rb" );
            if ( $handle === false ) return false;

            // Sets buffer size
            if ( !$adaptive ) $buffer = 4096;
            else $buffer = ( $lines < 2 ? 64 : ( $lines < 10 ? 512 : 4096 ) );

            // Jump to last character
            fseek( $handle, -1, SEEK_END );

            // Read it and adjust line number if necessary
            // (Otherwise the result would be wrong if file doesn't end with a blank line)
            if ( fread( $handle, 1 ) != "\n" ) $lines -= 1;

            // Start reading
            $output = '';
            $chunk = '';

            // While we would like more
            while ( ftell( $handle ) > 0 && $lines >= 0 ) {

                // Figure out how far back we should jump
                $seek = min( ftell( $handle ), $buffer );

                // Do the jump (backwards, relative to where we are)
                fseek( $handle, -$seek, SEEK_CUR );

                // Read a chunk and prepend it to our output
                $output = ( $chunk = fread( $handle, $seek ) ) . $output;

                // Jump back to where we started reading
                fseek( $handle, -mb_strlen( $chunk, '8bit' ), SEEK_CUR );

                // Decrease our line counter
                $lines -= substr_count( $chunk, "\n" );

            }

            // While we have too many lines
            // (Because of buffer size we might have read too many)
            while ( $lines++ < 0 ) {

                // Find first newline and remove all text before that
                $output = substr( $output, strpos( $output, "\n" ) + 1 );

            }

            // Close file and return
            fclose( $handle );
            return trim( $output );

        }

        static function highlight_log( $content ) {
            // Time stamp
            //$content = preg_replace( '/^(\S+ \S+)/im', '<em class="datetime">${1}</em>', $content );

            // Error Levels (Show timestamp)
            $content = preg_replace( '/^(\S+ \S+) PHP ([\w\s]+):(.+)$/im', '<br /><strong class="datetime">${1}</strong><br /><strong class="${2}">${2}</strong> ${3}', $content );

            // Stack trace (remove timestamp and indent)
            $content = preg_replace( '/^(\S+ \S+) PHP Stack trace:$/im', '&nbsp;&nbsp;&nbsp;&nbsp;<em class="info">Stack Trace</em>', $content );
            $content = preg_replace( '/^(\S+ \S+) PHP\s+(\d\.)(.+)$/im', '&nbsp;&nbsp;&nbsp;&nbsp;<small>${2}<span>${3}</span></small>', $content );

            /*
            $content = str_replace( 'PHP Parse error', '<strong class="error">PHP Parse error</strong>', $content );
            $content = str_replace( 'PHP Fatal error', '<strong class="error">PHP Fatal error</strong>', $content );
            $content = str_replace( 'PHP Warning', '<strong class="warning">PHP Warning</strong>', $content );
            $content = str_replace( 'PHP Notice', '<strong class="notice">PHP Notice</strong>', $content );
            */

            return $content;
        }

    }

    if ( WP_DEBUG ) {
        $DebugLogTail = new DebugLogTail();
    }
}
