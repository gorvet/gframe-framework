<?php

interface DatabaseConnectionInterface
{
    /**
     * @return PDO|array{status:string,message:string,code:int|string}
     */
    public function connect(array $config = []);
}
