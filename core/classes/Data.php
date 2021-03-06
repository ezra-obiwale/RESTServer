<?php

/**
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
interface Data {

    /**
     * Sets the target node
     */
    public static function setNode($node);

    /**
     * Sends the response out
     * @param mixed $response
     * @return void
     */
    public static function output($response);
}
