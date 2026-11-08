<?php
// Reachable only through a symlink that escapes the controller directory.
class Leaked
{
    public function index()
    {
        echo 'pwned';
    }
}
