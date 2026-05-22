app_name=oidc_claim_mapping
build_dir=build

npm-init:
	npm ci

npm-build:
	npm run build

composer-prod:
	composer install --no-dev --optimize-autoloader --no-interaction

appstore: npm-init npm-build
	rm -rf $(build_dir)
	mkdir -p $(build_dir)/artifacts
	rsync -a --exclude-from=.nextcloudignore . $(build_dir)/artifacts/$(app_name)
	cd $(build_dir)/artifacts/$(app_name) && composer install --no-dev --optimize-autoloader --no-interaction
	find $(build_dir)/artifacts/$(app_name)/vendor -type d -name .git -exec rm -rf {} + 2>/dev/null || true
	find $(build_dir)/artifacts/$(app_name)/vendor -type d \( -name test -o -name tests -o -name docs \) -exec rm -rf {} + 2>/dev/null || true
	cd $(build_dir)/artifacts && tar czf $(app_name).tar.gz $(app_name)
	rm -rf $(build_dir)/artifacts/$(app_name)
	@echo "Tarball: $(build_dir)/artifacts/$(app_name).tar.gz"

clean:
	rm -rf $(build_dir)

.PHONY: appstore clean npm-init npm-build composer-prod
