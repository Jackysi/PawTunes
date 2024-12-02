<?php

    /**
     * Hint IDE already defined variables from parent (this file is included)
     *
     * @var $this
     */

    // *** CLEAN CACHE & OLD USELESS FILES
    $this->sendSSE( '<div>Cleaning cache and temporary files...</div>' );
    
    // Delete the post-update script
    @unlink( $this->path . '/post-update.php' );