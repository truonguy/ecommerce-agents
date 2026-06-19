# Laravel E-commerce + CRM API

API thương mại điện tử 2 phân hệ (**Shop** cho khách hàng, **CRM** cho nội bộ), xây dựng theo
quy trình **spec-driven development** (Spec → Plan → Tasks → Implement, TDD từng task).

> Mã nguồn ứng dụng ở thư mục [`laravel-api/`](./laravel-api). Tài liệu spec & plan ở [`docs/`](./docs).

---

## Tổng quan

4 feature đã hoàn tất, cùng tồn tại trong một codebase, **268 tests / 664 assertions xanh**:

| # | Feature | Mô tả |
|---|---------|-------|
| 1 | **Auth & Authorization** | 2 phân hệ đăng nhập độc lập (Shop/CRM), Sanctum token, RBAC |
| 2 | **Product Catalog** | Category (nested), Product, Variant, Inventory, Media, Search |
| 3 | **Cart + Checkout + Order** | Giỏ hàng, đặt hàng (reserve tồn kho), vòng đời đơn hàng |
| 4 | **Payment + Reconciliation** | COD/VNPay, webhook, đối soát giao dịch |

Mỗi feature có tài liệu riêng trong `docs/`: `spec-*.md` (technical spec + acceptance) và `plan-*.md` (task breakdown).

---

## Tech Stack

- **Laravel 12** · **PHP 8.2+**
- **Laravel Sanctum** — token auth, 2 guard (`customer`, `employee`)
- **spatie/laravel-permission** — RBAC (roles + permissions)
- **intervention/image** — resize/optimize ảnh sản phẩm
- **MySQL** (dev: `laravel`, test: `laravel_test`)
- **PHPUnit** + **Pint** (code style)

---

## Kiến trúc

Phân lớp đồng nhất xuyên suốt mọi feature:

```
Controller  →  Service  →  Repository (interface)  →  Model
     ↑            ↑                ↑
 FormRequest   State Machine   Eloquent impl (bind ở AppServiceProvider)
              + Domain Exceptions (HTTP 4xx)
```

- **Controller** mỏng: validate (FormRequest) → gọi service → trả JSON.
- **Service**: logic nghiệp vụ, ném exception (không trả HTTP trực tiếp).
- **Repository**: truy cập DB qua interface, bind interface→Eloquent trong `AppServiceProvider`.
- **State Machine** (`OrderStateMachine`, `PaymentStateMachine`): gom luật chuyển trạng thái về một chỗ.
- **Domain exceptions** map sang HTTP (401/403/422) qua handler.
- **`PaginationService`** dùng chung cho mọi danh sách phân trang (envelope `{data, meta}`, clamp `per_page`).

---

## Feature chi tiết

### 1. Auth & Authorization
- **2 phân hệ tách biệt**: Shop (`customer` guard) ⇄ CRM (`employee` guard) — token chéo bị chặn (Sanctum provider).
- Login, **Register** (customer), **Forgot/Reset password** (broker riêng từng phân hệ), Logout / Logout-all.
- **RBAC**: roles `employee`/`admin`, permissions `manage_product`, `manage_order`, `manage_customer`, `manage_employee`, `system_config`, `publish_product`, `manage_inventory`.
- **Bảo mật**: generic error chống user-enumeration, rate-limit login, account lockout, token TTL (inactivity), audit log CRM.

### 2. Product Catalog
- **Category** nested (`parent_id`), **Product** (publish DRAFT/PUBLISHED/ARCHIVED), **Variant** (size/color/sku/price), **Inventory** (available/reserved per variant), **Media** (upload nhiều ảnh + resize, ảnh primary).
- **Shop public**: listing (filter/sort/paginate, giá = `min(variant.price)`), detail theo slug, search keyword.
- **Publish gate**: chuyển PUBLISHED cần quyền `publish_product` (employee thường không có).
- Soft delete toàn bộ catalog.

### 3. Cart + Checkout + Order
- **Cart** (1 active/customer): add (merge qty), update, remove, view (subtotal/count).
- **Checkout** trong `DB::transaction`: validate → **reserve tồn kho (`lockForUpdate`)** → snapshot giá → tạo order PENDING → clear cart. Thiếu tồn → rollback toàn bộ.
- **Order lifecycle** (state machine): `PENDING → CONFIRMED → PACKING → SHIPPING → DELIVERED` (+ `CANCELLED`).
  - cancel → release tồn; complete → consume tồn.
  - Customer huỷ order PENDING của mình; CRM vận hành confirm/pack/ship/complete/cancel (`manage_order`).
- **Idempotency**: header `Idempotency-Key` chống double-order; `lockForUpdate` chống oversell.

### 4. Payment + Reconciliation
- **Gateway abstraction** (interface `create`/`verify`/`query`): `CodAdapter`, `VnpayAdapter`, `GatewayManager`.
- **COD** → confirm order ngay. **VNPay** → trả `payment_url` → khách thanh toán → **webhook**.
- **Webhook = source of truth**: verify HMAC-SHA512, dedupe (`provider_txn_ref`), idempotent → SUCCESS thì confirm order.
- **CRM dashboard**: list/filter/detail/retry payment.
- **Reconciliation** (`php artisan payments:reconcile`): payment treo > timeout → query gateway / EXPIRED (eventual consistency).

---

## Cài đặt & chạy

```bash
cd laravel-api
composer install
cp .env.example .env          # cấu hình DB (MySQL) + gateway creds
php artisan key:generate
php artisan migrate --seed    # seed roles/permissions
php artisan serve
```

### Lệnh thường dùng
```bash
php artisan test                          # chạy toàn bộ test
php artisan test --filter=CheckoutTest    # chạy 1 nhóm
./vendor/bin/pint                         # format code
./vendor/bin/pint --test                  # kiểm style (không sửa)
php artisan payments:reconcile            # đối soát payment treo (cron)
```

> **Test** chạy trên MySQL `laravel_test` (môi trường này thiếu `pdo_sqlite`); cấu hình trong `phpunit.xml`.

---

## Cấu trúc thư mục (rút gọn)

```
docs/                         → spec-*.md + plan-*.md cho từng feature
srs-*.md                      → SRS gốc từ BA (đầu vào)
laravel-api/
  app/
    Enums/                    → UserStatus, PublishStatus, OrderStatus, PaymentStatus, PaymentMethod
    Http/Controllers/{Shop,Crm,Payment}/
    Http/Requests/{Shop,Crm,Auth}/
    Services/{Shop,Crm,Catalog,Order,Payment,Auth,Media,Support}/
    Repositories/{Contracts,Eloquent}/
    Models/                   → Customer, Employee, Product, Order, Payment, ...
  database/migrations/
  routes/api.php              → /api/shop/*, /api/crm/*, public catalog + webhook
  tests/Feature/{Auth,Product,Cart,Checkout,Order,Payment,Support}/
```

---

## Endpoints chính

| Phân hệ | Endpoint | Mô tả |
|---------|----------|-------|
| Shop | `POST /api/shop/auth/{register,login,logout}` | Auth customer |
| Shop | `POST /api/shop/auth/{forgot,reset}-password` | Quên/đặt lại mật khẩu |
| Public | `GET /api/products`, `GET /api/products/{slug}` | Catalog (chỉ PUBLISHED) |
| Shop | `GET/POST/PUT/DELETE /api/cart/items`, `GET /api/cart` | Giỏ hàng |
| Shop | `POST /api/checkout` | Đặt hàng |
| Shop | `GET /api/orders`, `POST /api/orders/{id}/cancel` | Đơn của khách |
| Shop | `POST /api/orders/{id}/payment` | Tạo thanh toán |
| Public | `POST /api/payment/webhook` | Callback gateway |
| CRM | `POST /api/crm/auth/login` | Auth employee |
| CRM | `…/categories, /products, /variants, /media` | Quản lý catalog (`manage_product`) |
| CRM | `PUT /api/crm/variants/{id}/inventory` | Tồn kho (`manage_inventory`) |
| CRM | `POST /api/crm/products/{id}/{publish,unpublish}` | Publish (`publish_product`) |
| CRM | `…/orders/{id}/{confirm,pack,ship,complete,cancel}` | Vận hành đơn (`manage_order`) |
| CRM | `GET /api/crm/payments`, `POST …/{id}/retry` | Dashboard payment (`manage_order`) |
| CRM | `GET/POST /api/crm/employees` | Quản lý nhân viên (`manage_employee`) |

---

## Quy trình phát triển

Mỗi feature đi theo `/spec` → `/plan` → `/build` (TDD: RED → GREEN → regression → mark done).
Tài liệu sống trong `docs/` được cập nhật song song với code. Quyết định kiến trúc & open questions
được ghi lại trong từng `spec-*.md` (§0 Quyết định, §9 trạng thái).

## Giới hạn đã biết (out-of-scope)
- Coverage số tự động cần Xdebug/PCOV (môi trường hiện chưa có).
- Payment: refund, installment, partial payment, wallet, auto-release reserve.
- VNPay cần creds thật (env) + API `query()` thật khi deploy.
