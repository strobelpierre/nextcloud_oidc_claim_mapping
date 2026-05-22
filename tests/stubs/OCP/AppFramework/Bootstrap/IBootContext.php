<?php

namespace OCP\AppFramework\Bootstrap;

interface IBootContext {
    public function getServerContainer(): \OCP\IServerContainer;
}
