import React from 'react';
import { Image, StyleSheet, Text, View, type ImageStyle, type StyleProp } from 'react-native';
import { API_URL } from '../config/api';
import type { Product } from '../api/products';
import { resolveProductImageUri } from '../api/products';

type Props = {
  product: Product;
  style?: StyleProp<ImageStyle>;
};

export function ProductImage({ product, style }: Props) {
  const uri = resolveProductImageUri(product, API_URL);

  if (!uri) {
    return (
      <View style={[styles.placeholder, style]}>
        <Text style={styles.placeholderText}>No image</Text>
      </View>
    );
  }

  return <Image source={{ uri }} style={[styles.image, style]} resizeMode="cover" accessibilityLabel={product.name} />;
}

const styles = StyleSheet.create({
  image: {
    width: '100%',
    aspectRatio: 1,
    borderRadius: 12,
    backgroundColor: '#f3f0ee',
  },
  placeholder: {
    width: '100%',
    aspectRatio: 1,
    borderRadius: 12,
    backgroundColor: '#efe6e1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    color: '#7a2535',
    fontSize: 13,
  },
});
