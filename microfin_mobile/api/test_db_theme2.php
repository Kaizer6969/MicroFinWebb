<?php
require 'db.php';
$conn->query("ALTER TABLE tenant_branding ADD COLUMN theme_border_color VARCHAR(50) DEFAULT '#E2E8F0'");
$conn->query("ALTER TABLE tenant_branding ADD COLUMN font_family VARCHAR(50) DEFAULT 'Inter'");
$conn->query("ALTER TABLE tenant_branding ADD COLUMN card_border_width VARCHAR(20) DEFAULT '0'");
$conn->query("ALTER TABLE tenant_branding ADD COLUMN card_shadow VARCHAR(100) DEFAULT 'none'");
echo "done config db";
?>
