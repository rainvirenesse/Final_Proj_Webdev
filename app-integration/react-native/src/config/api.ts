/**
 * Copy these files into your React Native (Expo) app project.
 * Set EXPO_PUBLIC_API_URL to your Symfony server (use LAN IP on a physical device).
 */
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
