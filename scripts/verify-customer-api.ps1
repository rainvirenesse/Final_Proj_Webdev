# Customer API smoke test (PowerShell)
# Usage: .\scripts\verify-customer-api.ps1 -BaseUrl "http://192.168.1.20:8000"

param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$Email = "john.doe@example.com",
    [string]$Password = "customer123"
)

$ErrorActionPreference = "Stop"
$BaseUrl = $BaseUrl.TrimEnd("/")
$passed = 0
$failed = 0

function Test-Step {
    param([string]$Name, [bool]$Ok)
    if ($Ok) {
        Write-Host "  [OK] $Name" -ForegroundColor Green
        $script:passed++
    } else {
        Write-Host "  [FAIL] $Name" -ForegroundColor Red
        $script:failed++
    }
}

Write-Host "`nCustomer API verification" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl`n"

# 1. Products
try {
    $products = Invoke-RestMethod -Uri "$BaseUrl/api/products" -Method Get
    Test-Step "GET /api/products -> 200" ($products.status -eq "success")
    $productId = ($products.data | Where-Object { $_.inStock -eq $true } | Select-Object -First 1).id
    if (-not $productId) { $productId = $products.data[0].id }
    Write-Host "  -> productId: $productId"
} catch {
    Test-Step "GET /api/products -> 200" $false
    $productId = $null
}

# 2. Login
$token = $null
try {
    $loginBody = @{ email = $Email; password = $Password } | ConvertTo-Json
    $login = Invoke-RestMethod -Uri "$BaseUrl/api/login" -Method Post -Body $loginBody -ContentType "application/json"
    $token = if ($login.token) { $login.token } else { $login.data.token }
    Test-Step "POST /api/login -> token" ($null -ne $token -and $token -ne "")
} catch {
    Test-Step "POST /api/login -> token" $false
}

if (-not $token) {
    Write-Host "`n$passed passed, $failed failed. Fix login before continuing.`n" -ForegroundColor Yellow
    exit 1
}

$headers = @{ Authorization = "Bearer $token"; Accept = "application/json" }

# 3. Profile
try {
    $profile = Invoke-RestMethod -Uri "$BaseUrl/api/customer/profile" -Headers $headers
    Test-Step "GET /api/customer/profile -> 200" ($profile.status -eq "success")
} catch { Test-Step "GET /api/customer/profile -> 200" $false }

if ($productId) {
    # 4. Cart
    try {
        $cartBody = @{ productId = [int]$productId; quantity = 1 } | ConvertTo-Json
        $cart = Invoke-RestMethod -Uri "$BaseUrl/api/cart/items" -Method Post -Headers $headers -Body $cartBody -ContentType "application/json"
        Test-Step "POST /api/cart/items -> 201" ($true)
    } catch { Test-Step "POST /api/cart/items -> 201" $false }

    # 5. Order
    try {
        $orderBody = @{ notes = "PowerShell verify" } | ConvertTo-Json
        $order = Invoke-RestMethod -Uri "$BaseUrl/api/orders" -Method Post -Headers $headers -Body $orderBody -ContentType "application/json"
        $orderId = $order.data.id
        Test-Step "POST /api/orders -> 201" ($null -ne $orderId)
    } catch { Test-Step "POST /api/orders -> 201" $false; $orderId = $null }

    if ($orderId) {
        try {
            $payBody = @{ orderId = [int]$orderId; method = "gcash"; reference = "PS-VERIFY" } | ConvertTo-Json
            Invoke-RestMethod -Uri "$BaseUrl/api/payments" -Method Post -Headers $headers -Body $payBody -ContentType "application/json" | Out-Null
            Test-Step "POST /api/payments -> 201" ($true)
        } catch { Test-Step "POST /api/payments -> 201" $false }
    }
}

Write-Host ""
if ($failed -eq 0) {
    Write-Host "All $passed check(s) passed. Ready for demo.`n" -ForegroundColor Green
} else {
    Write-Host "$passed passed, $failed failed. See README.md`n" -ForegroundColor Yellow
}

exit $(if ($failed -gt 0) { 1 } else { 0 })
