<?php

namespace OCP;

interface IServerContainer {
    public function get(string $id);
}
