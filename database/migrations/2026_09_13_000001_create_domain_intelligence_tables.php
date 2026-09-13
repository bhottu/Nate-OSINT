<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain Intelligence schema.
     *
     * Tables are prefixed with `domain_intel_` to avoid colliding with the
     * legacy `domains` and `domain_dns_records` tables that ship with the app.
     */
    public function up(): void
    {
        Schema::create('domain_intel_scans', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('hostname')->index();
            $table->string('input')->nullable();
            $table->string('status')->default('QUEUED');
            $table->string('stage')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->integer('score')->nullable();
            $table->string('grade')->nullable();
            $table->string('posture')->nullable();
            $table->json('summary')->nullable();
            $table->json('meta')->nullable();
            $table->text('error')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_intel_dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->string('type')->index();
            $table->string('name')->nullable();
            $table->text('value')->nullable();
            $table->unsignedInteger('ttl')->default(0);
            $table->string('source')->default('DNS');
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->index(['scan_id', 'hostname', 'type']);
        });

        Schema::create('domain_intel_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->string('source')->nullable();
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->string('dns_status')->nullable();
            $table->json('a')->nullable();
            $table->json('aaaa')->nullable();
            $table->json('cname')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('title')->nullable();
            $table->json('technologies')->nullable();
            $table->string('ip')->nullable();
            $table->string('asn')->nullable();
            $table->string('cdn')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->timestamps();
            $table->unique(['scan_id', 'hostname']);
        });

        Schema::create('domain_intel_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('ip')->index();
            $table->unsignedTinyInteger('version')->default(4);
            $table->string('asn')->nullable()->index();
            $table->string('asn_org')->nullable();
            $table->string('isp')->nullable();
            $table->string('hosting')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('reverse_dns')->nullable();
            $table->string('network')->nullable();
            $table->string('prefix')->nullable();
            $table->string('rir')->nullable();
            $table->string('source')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->timestamps();
            $table->unique(['scan_id', 'ip']);
        });

        Schema::create('domain_intel_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->string('issuer')->nullable();
            $table->string('subject')->nullable();
            $table->json('san')->nullable();
            $table->boolean('wildcard')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('serial')->nullable();
            $table->string('fingerprint')->nullable();
            $table->string('source')->default('Certificate Transparency');
            $table->timestamps();
        });

        Schema::create('domain_intel_whois_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('registrar')->nullable();
            $table->string('registrar_url')->nullable();
            $table->json('status')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('expiration_date')->nullable();
            $table->timestamp('updated_date')->nullable();
            $table->json('nameservers')->nullable();
            $table->string('registry')->nullable();
            $table->boolean('dnssec')->default(false);
            $table->string('abuse_contact')->nullable();
            $table->boolean('privacy_protected')->default(false);
            $table->json('raw')->nullable();
            $table->string('source')->default('RDAP');
            $table->timestamps();
        });

        Schema::create('domain_intel_technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('technology')->index();
            $table->string('category')->nullable();
            $table->text('evidence')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->timestamps();
        });

        Schema::create('domain_intel_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('title');
            $table->string('severity')->index();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('evidence')->nullable();
            $table->string('source')->nullable();
            $table->string('affected_asset')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->text('remediation')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_intel_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->text('raw_value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->timestamps();
        });

        Schema::create('domain_intel_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('from_node')->index();
            $table->string('from_type');
            $table->string('to_node')->index();
            $table->string('to_type');
            $table->string('relationship');
            $table->text('evidence')->nullable();
            $table->string('confidence')->default('UNKNOWN');
            $table->timestamps();
            $table->unique(['scan_id', 'from_node', 'to_node', 'relationship'], 'di_rel_unique');
        });

        Schema::create('domain_intel_email_security', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('spf_status')->default('UNKNOWN');
            $table->text('spf_value')->nullable();
            $table->string('dmarc_status')->default('UNKNOWN');
            $table->text('dmarc_value')->nullable();
            $table->string('mx_status')->default('UNKNOWN');
            $table->string('dkim_status')->default('UNKNOWN');
            $table->json('dkim_selectors')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_intel_http_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->string('scheme')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('https')->default(false);
            $table->json('redirects')->nullable();
            $table->string('server')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('content_length')->nullable();
            $table->string('location')->nullable();
            $table->json('cache')->nullable();
            $table->json('security_headers')->nullable();
            $table->json('cookies')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_intel_tls_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('domain_intel_scans')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->boolean('valid')->default(false);
            $table->string('issuer')->nullable();
            $table->string('subject')->nullable();
            $table->json('san')->nullable();
            $table->string('fingerprint')->nullable();
            $table->string('tls_version')->nullable();
            $table->json('chain')->nullable();
            $table->boolean('hostname_match')->default(false);
            $table->timestamp('not_before')->nullable();
            $table->timestamp('not_after')->nullable();
            $table->integer('days_remaining')->nullable();
            $table->boolean('ct_present')->default(false);
            $table->timestamps();
        });

        Schema::create('domain_intel_watchlists', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('frequency')->default('WEEKLY');
            $table->boolean('active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('last_score')->nullable();
            $table->json('last_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_intel_watchlists');
        Schema::dropIfExists('domain_intel_tls_observations');
        Schema::dropIfExists('domain_intel_http_observations');
        Schema::dropIfExists('domain_intel_email_security');
        Schema::dropIfExists('domain_intel_relationships');
        Schema::dropIfExists('domain_intel_evidences');
        Schema::dropIfExists('domain_intel_findings');
        Schema::dropIfExists('domain_intel_technologies');
        Schema::dropIfExists('domain_intel_whois_records');
        Schema::dropIfExists('domain_intel_certificates');
        Schema::dropIfExists('domain_intel_ips');
        Schema::dropIfExists('domain_intel_subdomains');
        Schema::dropIfExists('domain_intel_dns_records');
        Schema::dropIfExists('domain_intel_scans');
    }
};