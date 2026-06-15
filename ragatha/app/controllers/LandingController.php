<?php

class LandingController
{
    public function index(): void
    {
        $filePath = ROOT_PATH . '/app/views/public/landing_amare.html';
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "Landing page not found";
            return;
        }
        
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
    }
}
