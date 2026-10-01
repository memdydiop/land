# AGENTS.md

## 1. Project Overview

This repository contains a multi-tenant SaaS application dedicated to the BTP, construction, civil engineering, land development, subdivision, and real-estate sectors.

The application is designed as a production-grade SaaS platform.

The architecture is intentionally modular and domain-oriented.

---

# 2. Technology Stack

The following technologies are mandatory unless explicitly changed by the project owner:

* PHP 8.3+
* Laravel 13
* Livewire 4
* Laravel Livewire Starter Kit
* Livewire SFC-first architecture
* Flux UI Free
* PostgreSQL
* PostGIS
* Redis
* Stancl Tenancy 3.10.1
* Spatie Laravel Permission
* Vite
* Tailwind CSS

Do not introduce another major framework or architectural approach without explicit approval.

---

# 3. Multi-Tenancy

## 3.1 Architecture

The application uses:

**PostgreSQL schema-per-tenant.**

The PostgreSQL database contains:

```text
public
├── tenants
├── domains
├── plans
├── subscriptions
├── subscription_items
├── platform_users
├── platform_settings
├── billing_invoices
└── billing_payments
```

Each tenant receives its own PostgreSQL schema:

```text
tenant_xxx
tenant_yyy
tenant_zzz
```

Tenant business data must never be stored in the Landlord schema.

---

## 3.2 Landlord vs Tenant

### Landlord

The Landlord schema contains SaaS/platform data only:

* tenants
* domains
* plans
* subscriptions
* subscription_items
* platform_users
* platform_settings
* billing_invoices
* billing_payments

### Tenant

Each tenant schema contains business data:

* users
* roles
* permissions
* settings
* CRM
* projects
* construction sites
* land
* subdivision
* real estate
* procurement
* finance
* documents
* audit logs
* notifications

Never mix SaaS billing with tenant operational finance.

---

# 4. Tenant Identity

Version 1 uses:

**One user = one tenant.**

Do not introduce memberships, teams, organizations pivot tables, or multi-company users unless explicitly requested.

---

# 5. Tenant Provisioning

Tenant provisioning must be handled through Stancl Tenancy.

The expected lifecycle is:

```text
Registration
    ↓
Create Tenant
    ↓
Create Domain
    ↓
TenantCreated
    ↓
Create PostgreSQL schema
    ↓
Run tenant migrations
    ↓
Run tenant seeders
    ↓
Create owner user
    ↓
Initialize settings
    ↓
Redirect to tenant dashboard
```

Do not manually duplicate Stancl's tenant lifecycle logic in controllers.

Business-specific provisioning should be implemented through dedicated actions/jobs/listeners.

---

# 6. PostgreSQL

PostgreSQL is the primary database.

Do not use MySQL-specific SQL, migrations, indexes, or assumptions.

Use PostgreSQL-native capabilities where appropriate:

* JSONB
* INET
* partial indexes
* CHECK constraints
* GiST indexes
* PostGIS
* transactional DDL where appropriate

---

# 7. ULID Policy

All application primary keys use ULID.

Use Laravel native migration helpers:

```php
$table->ulid('id')->primary();
```

For foreign keys:

```php
$table->foreignUlid('project_id');
```

Do not replace ULIDs with UUIDs or auto-incrementing integers.

Do not create a custom ULID database type.

Laravel's native ULID implementation is the project standard.

---

# 8. Foreign Keys

Use foreign-key behavior according to business semantics.

### CASCADE

Use only for strict child/dependent records.

Examples:

* subscription → subscription_items
* document → document_versions

### SET NULL

Use for optional references where deleting the parent should preserve the record.

### RESTRICT

Use for historical or financially significant records.

Examples:

* invoices
* payments
* contracts
* accounting-related history
* audit records

Never blindly use cascade deletion.

---

# 9. Statuses and Enums

Business statuses must be implemented as PHP Backed Enums.

Example:

```php
enum ProjectStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
```

Database representation:

```text
VARCHAR
```

Do NOT create PostgreSQL ENUM types for business statuses.

Models should cast status fields to their corresponding PHP Enum.

---

# 10. State Transitions

Do not allow arbitrary status mutation from controllers or Livewire components.

Incorrect:

```php
$model->status = 'completed';
$model->save();
```

Business state changes must go through Domain Actions.

Example:

```php
CompleteProjectAction
ApprovePurchaseRequestAction
IssueInvoiceAction
ConfirmPaymentAction
ApproveLandOperationAction
```

Actions are responsible for:

* validating preconditions
* authorizing the operation
* changing state
* persisting changes
* dispatching domain events where necessary

---

# 11. Domain Architecture

Business logic must not be concentrated inside:

* Controllers
* Livewire components
* Eloquent models

Use domain-oriented Actions and Services.

Recommended structure:

```text
app/
├── Domain/
│   ├── Organization/
│   ├── CRM/
│   ├── Projects/
│   ├── Construction/
│   ├── Land/
│   ├── RealEstate/
│   ├── Procurement/
│   ├── Finance/
│   ├── Documents/
│   └── Platform/
│
├── Livewire/
├── Models/
├── Policies/
├── Enums/
├── Jobs/
├── Events/
└── Notifications/
```

Exact namespace structure may evolve, but domain separation must remain clear.

---

# 12. Livewire Architecture

The project follows:

**Livewire SFC-first.**

Use Single File Components where appropriate.

Livewire components are responsible for:

* UI state
* validation
* user interaction
* invoking domain actions
* presenting data

They must not contain substantial business rules.

Avoid God Components.

If a component becomes too complex, extract:

* Actions
* Services
* Queries
* Form objects
* reusable components

---

# 13. Controllers

Controllers are intentionally minimal.

Controllers may handle:

* routing
* authorization entry points
* HTTP-specific concerns
* redirects
* invoking actions

Do not put complex business logic into controllers.

For authenticated application functionality, prefer Livewire SFCs when appropriate.

---

# 14. Public Website

The public SaaS marketing website is separate from tenant application functionality.

Expected public routes:

```text
/
 /features
 /solutions
 /sectors
 /pricing
 /about
 /faq
 /contact
 /login
 /register
```

Public pages must be:

* SEO-friendly
* server-rendered where appropriate
* fast
* accessible
* responsive

Do not introduce tenant context for public marketing pages.

---

# 15. Authentication

There are two conceptual user populations:

### Platform users

Stored in:

```text
platform_users
```

Used for SaaS administration.

### Tenant users

Stored in each tenant schema:

```text
users
```

Used by the customer organization.

Do not merge these identities.

---

# 16. Authorization

Use:

**Spatie Laravel Permission**

for tenant authorization.

Roles and permissions belong to the tenant context.

Authorization must also be enforced through Laravel Policies.

Never rely exclusively on hiding buttons or UI elements.

Every sensitive operation must be authorized server-side.

---

# 17. CRM Architecture

CRM uses the `parties` abstraction.

Core entities:

```text
parties
├── companies
├── contacts
└── party_relationships
```

Clients, suppliers, prospects, owners, occupants and partners must reference `parties`.

Do not create separate unrelated client/supplier tables unless explicitly approved.

---

# 18. Financial Architecture

There are two completely separate financial domains.

## SaaS Billing

Landlord:

```text
billing_invoices
billing_payments
```

These represent the customer's subscription to the SaaS platform.

## Tenant Finance

Tenant:

```text
budgets
budget_items
quotes
quote_items
contracts
contract_items
expenses
invoices
invoice_items
payments
```

These represent the tenant company's own commercial and operational finances.

Never mix these domains.

---

# 19. PostGIS

PostGIS is mandatory for spatial functionality.

Canonical SRID:

```text
EPSG:4326
```

Use appropriate geometry types:

```text
Point
MultiPolygon
MultiLineString
```

Spatial columns must use SRID 4326.

Validate geometry server-side.

Important spatial columns must have GiST indexes.

Never trust geometry received directly from the browser.

---

# 20. Spatial Business Rules

Examples:

* land boundaries must be valid
* parcel boundaries must be valid
* subdivision geometry must be valid
* roads must use appropriate line geometry
* networks must use appropriate line geometry
* facilities may use polygon geometry

Where relevant, validate:

```text
ST_IsValid
```

and normalize geometry where appropriate.

---

# 21. Documents

Documents use PostgreSQL for metadata only.

Actual files must be stored in object storage.

Example:

```text
S3-compatible storage
        │
        └── document file
```

PostgreSQL stores:

* filename
* MIME type
* size
* checksum
* storage disk
* storage path
* version
* uploader
* status

Do not store large binary documents directly in PostgreSQL.

Documents use polymorphic relations:

```text
documentable_type
documentable_id
```

Do not introduce polymorphic relations elsewhere without architectural justification.

---

# 22. Document Versioning

Documents support versions.

Expected relationship:

```text
Document
   │
   ├── Version 1
   ├── Version 2
   ├── Version 3
   └── current_version
```

Versions are immutable after upload.

Do not overwrite historical document versions.

---

# 23. Audit

Important business operations must be auditable.

Use:

```text
activity_logs
```

Audit records should capture where appropriate:

* actor
* event
* subject
* old values
* new values
* IP address
* user agent
* timestamp

Audit logs should be treated as historical records.

Do not casually delete them.

---

# 24. Notifications

Use Laravel Notifications.

Notification records belong to the tenant context.

Notification IDs may use Laravel's standard UUID notification ID.

The notifiable entity ID remains compatible with tenant ULIDs.

---

# 25. Database Migration Rules

Migrations must be deterministic and ordered.

Landlord migrations:

```text
database/migrations/landlord/
```

Tenant migrations:

```text
database/migrations/tenant/
```

Do not put tenant business tables into Landlord migrations.

Do not put SaaS billing tables into tenant migrations.

Every migration must have a working `down()` method unless PostgreSQL or the framework makes rollback fundamentally unsafe.

---

# 26. Migration Order

## Landlord

```text
1. postgis
2. tenants
3. domains
4. plans
5. subscriptions
6. subscription_items
7. platform_users
8. platform_settings
9. billing_invoices
10. billing_payments
```

## Tenant

```text
1. users
2. roles / permissions
3. settings

4. parties
5. companies
6. contacts
7. party_relationships

8. projects
9. project_members
10. project_phases
11. tasks
12. milestones

13. construction_sites
14. work_packages
15. site_logs
16. progress_reports

17. lands
18. land_operations
19. land_operation_lands
20. subdivisions
21. blocks
22. parcels
23. roads
24. networks
25. facilities

26. properties
27. buildings
28. units
29. property_owners
30. occupancies

31. purchase_requests
32. supplier_quotes
33. purchase_orders
34. purchase_order_items
35. goods_receipts

36. budgets
37. budget_items
38. quotes
39. quote_items
40. contracts
41. contract_items
42. expenses
43. invoices
44. invoice_items
45. payments

46. document_categories
47. documents
48. document_versions

49. activity_logs
50. notifications
```

---

# 27. Naming Conventions

Use Laravel conventions.

Tables:

```text
snake_case
plural
```

Examples:

```text
construction_sites
purchase_orders
document_versions
```

Models:

```text
StudlyCase
singular
```

Examples:

```text
ConstructionSite
PurchaseOrder
DocumentVersion
```

Foreign keys:

```text
{singular_model}_id
```

Examples:

```text
project_id
tenant_id
construction_site_id
```

---

# 28. Money

Never use floating-point values for monetary amounts.

Use:

```php
$table->decimal('amount', 15, 2);
```

or the project's approved money value object where applicable.

Totals must be calculated server-side.

Never trust totals sent by the frontend.

---

# 29. Dates and Timezones

Store timestamps using PostgreSQL timestamp with timezone where appropriate.

Tenant timezone is stored on:

```text
tenants.timezone
```

Default:

```text
Africa/Abidjan
```

Business date calculations must respect the tenant timezone.

Do not hardcode the application timezone into business logic.

---

# 30. References vs IDs

ULIDs are technical identifiers.

Business references are separate.

Example:

```text
id:
01KXXXX...

reference:
PRJ-2026-0001
```

Do not expose database IDs as business references.

References must be unique within the appropriate tenant/domain.

---

# 31. Validation

Validate at multiple levels:

1. Livewire/form validation
2. Domain Action validation
3. Database constraints

The frontend must never be considered a security boundary.

Critical invariants must exist at database or domain level.

---

# 32. Transactions

Use database transactions for multi-step business operations.

Examples:

```text
Create project
Approve purchase order
Issue invoice
Register payment
Create subdivision
Create tenant
```

A business operation should either complete consistently or roll back.

---

# 33. Events and Jobs

Use Events for domain events.

Use Jobs for:

* long-running work
* document processing
* notifications
* asynchronous provisioning
* imports/exports
* external integrations

Use Redis-backed queues in production.

Do not dispatch unnecessary jobs for trivial synchronous operations.

---

# 34. Testing

Every major domain action must have automated tests.

Minimum testing layers:

```text
Unit
Feature
Authorization
Database
Tenancy
PostGIS
Provisioning
```

Critical tests must verify tenant isolation.

Example:

```text
Tenant A cannot access Tenant B data.
```

This is a fundamental security requirement.

---

# 35. Tenant Isolation

Tenant isolation is non-negotiable.

Never allow a tenant request to query another tenant schema.

Never rely solely on:

```php
where('tenant_id', ...)
```

because tenant data is isolated by PostgreSQL schema.

Stancl's tenant context must be active before tenant business queries execute.

---

# 36. Security

Never:

* trust tenant IDs supplied by the browser
* trust monetary totals from the browser
* trust status transitions from the browser
* expose unrestricted model queries
* bypass Policies
* disable CSRF protection unnecessarily
* store secrets in source control
* commit `.env`

Use Laravel's standard security mechanisms.

---

# 37. Code Quality

Prefer:

* small classes
* explicit dependencies
* typed properties
* typed method parameters
* return types
* readonly values where appropriate
* PHP Enums
* Form Requests / validation objects where appropriate
* Actions
* Policies
* automated tests

Avoid:

* God classes
* God Livewire components
* massive controllers
* duplicated business rules
* unnecessary abstractions
* premature generic repositories

---

# 38. Before Modifying the Repository

Codex must first inspect:

```text
composer.json
package.json
.env.example
config/
app/
database/
resources/
routes/
tests/
```

Before implementing a feature:

1. inspect existing architecture
2. identify relevant files
3. check installed package versions
4. avoid duplicating existing functionality
5. propose architectural changes when necessary
6. implement incrementally
7. run relevant tests

---

# 39. Important Rule for Codex

Do not make large architectural changes silently.

If an implementation conflicts with this document:

1. stop
2. explain the conflict
3. propose the smallest viable correction
4. wait for approval when the change affects architecture

Do not silently replace:

* PostgreSQL with another DB
* schema-per-tenant with database-per-tenant
* ULID with UUID
* Livewire SFC with another frontend architecture
* Stancl Tenancy
* Spatie Permission
* PostGIS

---

# 40. Current Product Scope

## MVP

### Organization

* tenant
* users
* roles
* permissions
* settings

### CRM

* parties
* companies
* contacts
* relationships

### Projects

* projects
* members
* phases
* tasks
* milestones

### Construction

* construction sites
* work packages
* site logs
* progress reports

### Land

* lands
* land operations
* subdivisions
* blocks
* parcels
* roads
* networks
* facilities

### Real Estate

* properties
* buildings
* units
* owners
* occupancies

### Procurement

* purchase requests
* supplier quotes
* purchase orders
* receipts

### Finance

* budgets
* quotes
* contracts
* expenses
* invoices
* payments

### Documents

* categories
* documents
* versions

### Cross-cutting

* audit
* notifications
* PostGIS
* reporting

---

# 41. Explicitly Out of Scope for MVP

Do not implement unless explicitly requested:

* full accounting ERP
* payroll
* BIM
* CAD/DAO
* full professional GIS
* mobile application
* public API
* advanced Gantt
* marketplace
* AI prediction
* IoT
* GPS fleet management
* advanced accounting integrations

These may be future versions.

---

# 42. Development Strategy

Development must follow this sequence:

```text
Architecture
    ↓
Landlord
    ↓
Tenant provisioning
    ↓
Tenant migrations
    ↓
Enums
    ↓
Models
    ↓
Relations
    ↓
Policies / Permissions
    ↓
Domain Actions
    ↓
Events / Jobs
    ↓
Livewire SFC
    ↓
UI
    ↓
Tests
```

Do not jump directly to UI implementation before the underlying domain model is stable.

---

# 43. Definition of Done

A feature is not considered complete merely because the UI works.

A feature is complete when:

* database structure is correct
* tenant isolation is respected
* validation exists
* authorization exists
* domain logic is extracted appropriately
* state transitions are controlled
* tests exist
* relevant migrations run
* rollback is considered
* static analysis/code quality is acceptable
* no unrelated files are modified

---

# 44. Current Implementation Phase

The project is currently at:

```text
CDC
 ↓
ERD V1.1
 ↓
Data Dictionary V1
 ↓
Architecture decisions
 ↓
AGENTS.md
 ↓
CURRENT STEP
```

The next implementation step is:

```text
Landlord infrastructure
        ↓
Stancl configuration
        ↓
Tenant provisioning
        ↓
Landlord migrations
        ↓
Tenant migrations
```

Do not begin implementing all 50 tenant migrations until tenant provisioning has been verified.

---

# 45. First Task for Codex

When asked to begin implementation:

1. Inspect the repository.
2. Verify installed versions.
3. Compare the existing project against this AGENTS.md.
4. Report discrepancies.
5. Do not overwrite existing architecture blindly.
6. Implement Landlord infrastructure first.
7. Configure Stancl Tenancy.
8. Configure PostgreSQL schema-per-tenant.
9. Configure PostGIS.
10. Create and test tenant provisioning.
11. Only then proceed to tenant migrations.

The project owner must approve significant architectural deviations.
