# React Native — product images (Symfony backend)

Copy `src/` into your App Development (Expo / React Native) project, then wire screens to use `fetchProducts()` and `<ProductImage />`.

## 1. Environment

In your mobile app `.env`:

```env
# Local LAN
EXPO_PUBLIC_API_URL=http://192.168.1.25:8000

# Railway production (see docs/RAILWAY.md in Symfony repo)
# EXPO_PUBLIC_API_URL=https://your-app.up.railway.app
```

- **Android emulator:** `http://10.0.2.2:8000`
- **iOS simulator:** `http://127.0.0.1:8000`
- **Physical device:** your PC’s LAN IP (Symfony must run with `symfony server:start --host=0.0.0.0`)
- **Railway:** use the public HTTPS domain; ensure Symfony `APP_URL` matches

In Symfony `.env.local` (same machine as the API):

```env
APP_URL=http://192.168.1.25:8000
```

`APP_URL` is used when building absolute `imageUrl` values if no HTTP request context exists.

## 2. Customer API (recommended for mobile)

Use the dedicated customer REST API under `/api/customer/*` (see `docs/API_CUSTOMER.md` in the Symfony project).

Copy `src/api/client.ts`, `auth.ts`, `cart.ts`, `orders.ts`, `profile.ts`, `payments.ts`, and updated `products.ts`.

**Login token:** After `POST /api/login`, store `parseLoginToken(response)` (see `auth.ts`) and pass it to every cart/order call. If you skip the token, add-to-cart returns **401** and many apps show “session expired”.

**Customer account only:** Cart routes require `ROLE_CUSTOMER`. Staff/admin JWTs get **403 Access denied**.

## 3. Product image fields

`GET /api/customer/products` returns each product with:

| Field | Description |
|--------|-------------|
| `image` | Value stored in the database |
| `imagePath` | Web path under `public/` (e.g. `images/products/foo.jpg`) |
| `imageUrl` | Full URL for the mobile app — **use this in `<Image uri={...} />`** |

Upload a product image in the **admin/staff** web panel (Products → Edit → Product image). The file is stored under `public/images/products/` and appears in the API immediately.

## 4. Example list screen

```tsx
import { useEffect, useState } from 'react';
import { FlatList, Text, View } from 'react-native';
import { fetchProducts, type Product } from './src/api/products';
import { ProductImage } from './src/components/ProductImage';

export default function ProductsScreen() {
  const [products, setProducts] = useState<Product[]>([]);

  useEffect(() => {
    fetchProducts().then(setProducts).catch(console.error);
  }, []);

  return (
    <FlatList
      data={products}
      keyExtractor={(item) => String(item.id)}
      renderItem={({ item }) => (
        <View style={{ padding: 16 }}>
          <ProductImage product={item} />
          <Text>{item.name}</Text>
        </View>
      )}
    />
  );
}
```

## 5. Verify

1. Upload an image on the web admin product form.
2. Open `http://YOUR_HOST:8000/api/products` in a browser — confirm `imageUrl` is present.
3. Open the same URL in the phone browser — image URL must load.
4. Run the mobile app with matching `EXPO_PUBLIC_API_URL`.
