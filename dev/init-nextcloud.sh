#!/bin/bash
set -e

echo "=== Initializing Nextcloud for OIDC Claim Mapping development ==="

# Wait for NC to be ready
until php occ status 2>/dev/null | grep -q "installed: true"; do
    echo "Waiting for Nextcloud to be installed..."
    sleep 5
done

# Fix permissions
chown -R www-data:www-data /var/www/html/apps /var/www/html/custom_apps

# Install and enable user_oidc
echo "Installing user_oidc..."
php occ app:install user_oidc || true
php occ app:enable user_oidc || true

# Allow insecure HTTP for dev
php occ config:app:set user_oidc allow_insecure_http --value="1" --type=boolean
php occ config:system:set allow_local_remote_servers --value=true --type=boolean

# Configure OIDC provider pointing to local Keycloak
echo "Configuring OIDC provider..."
php occ user_oidc:provider nextcloud-keycloak \
    --clientid="nextcloud" \
    --clientsecret="ff75b7c7-20f9-460b-b27c-16bd5d9b4cd0" \
    --discoveryuri="http://keycloak:8080/realms/nextcloudci/.well-known/openid-configuration" \
    --unique-uid=0 \
    --group-provisioning=1 \
    --mapping-groups="groups"

# Enable our app
echo "Enabling oidc_claim_mapping..."
php occ app:enable oidc_claim_mapping || true

# Configure sample rules — each rule MUST have `target` (M2 validation).
# These sample rules demonstrate scalar-attribute overrides over the
# user_oidc native mappings; the AttributeMappingListener that actually
# applies them is wired in at M3.
echo "Setting sample mapping rules..."
php occ config:app:set oidc_claim_mapping mapping_rules --value='{
  "version": 1,
  "mode": "additive",
  "rules": [
    {
      "id": "displayname-prefix",
      "type": "prefix",
      "target": "displayName",
      "providerIdentifier": "*",
      "enabled": true,
      "claimPath": "name",
      "config": {
        "prefix": "[DEV] "
      }
    },
    {
      "id": "email-direct",
      "type": "direct",
      "target": "email",
      "providerIdentifier": "*",
      "enabled": true,
      "claimPath": "email",
      "config": {}
    },
    {
      "id": "country-from-locale",
      "type": "map",
      "target": "address",
      "providerIdentifier": "*",
      "enabled": true,
      "claimPath": "locale",
      "config": {
        "map": {
          "en-US": "United States",
          "fr-FR": "France",
          "de-DE": "Germany"
        }
      }
    }
  ]
}'

echo ""
echo "=== Setup complete ==="
echo "NC:  http://localhost:8081  (admin/admin)"
echo "KC:  http://localhost:8999  (admin/admin)"
echo "Test: Login via 'Log in with nextcloud-keycloak' using testuser1/password"
