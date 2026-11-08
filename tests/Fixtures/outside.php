<?php
// Lives OUTSIDE the controller directory. Reaching it through the router
// would be a path traversal.
class Outside
{
    public function index()
    {
        echo 'pwned';
    }
}
