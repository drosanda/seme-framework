<?php
// File name says "broken" but the class does not match: exercises the
// "Unable to load class" branch.
class WrongName extends SENE_Controller
{
    public function index()
    {
        echo 'never';
    }
}
