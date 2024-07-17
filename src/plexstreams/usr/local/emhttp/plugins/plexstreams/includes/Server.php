<?php

interface Server {

    public function getGeo($ipAddress);
    public function getConfig();
    public function getMediaDetails($media);
}