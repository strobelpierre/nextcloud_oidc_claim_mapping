<?php

namespace OCP\App;

interface IAppManager {
    public function isEnabledForUser(string $appId, $user = null): bool;
}
