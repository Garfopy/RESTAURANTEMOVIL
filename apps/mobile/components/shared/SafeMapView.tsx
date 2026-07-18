import React from 'react';
import { StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import type { Region } from 'react-native-maps';

declare const require: (name: string) => any;

type ReactNativeMapsModule = typeof import('react-native-maps');

let reactNativeMapsModule: ReactNativeMapsModule | null | undefined;

function getReactNativeMapsModule(): ReactNativeMapsModule | null {
  if (reactNativeMapsModule !== undefined) {
    return reactNativeMapsModule;
  }

  try {
    reactNativeMapsModule = require('react-native-maps') as ReactNativeMapsModule;
  } catch (error) {
    reactNativeMapsModule = null;
    if (__DEV__) {
      console.warn('[Map] react-native-maps no esta disponible en este build:', error);
    }
  }

  return reactNativeMapsModule;
}

class MapErrorBoundary extends React.Component<
  { children: React.ReactNode; fallback: React.ReactNode },
  { hasError: boolean }
> {
  state = { hasError: false };

  static getDerivedStateFromError(): { hasError: boolean } {
    return { hasError: true };
  }

  componentDidCatch(error: unknown) {
    console.warn('[Map] Error renderizando mapa:', error);
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback;
    }

    return this.props.children;
  }
}

type SafeMapViewProps = {
  region: Region;
  style: StyleProp<ViewStyle>;
  onRegionChangeComplete?: (region: Region) => void;
  showsUserLocation?: boolean;
  rotateEnabled?: boolean;
  pitchEnabled?: boolean;
  toolbarEnabled?: boolean;
  fallbackText?: string;
  children?: React.ReactNode;
};

export function SafeMapView({
  region,
  style,
  onRegionChangeComplete,
  showsUserLocation = false,
  rotateEnabled = false,
  pitchEnabled = false,
  toolbarEnabled,
  fallbackText = 'Mapa no disponible por el momento.',
  children,
}: SafeMapViewProps) {
  const mapsModule = getReactNativeMapsModule();
  const fallback = (
    <View style={[style, styles.fallback]}>
      <Text style={styles.fallbackText}>{fallbackText}</Text>
    </View>
  );

  if (!mapsModule) {
    return fallback;
  }

  const MapView = mapsModule.default as React.ComponentType<any>;

  try {
    return (
      <MapErrorBoundary fallback={fallback}>
        <MapView
          style={style}
          region={region}
          onRegionChangeComplete={onRegionChangeComplete}
          showsUserLocation={showsUserLocation}
          rotateEnabled={rotateEnabled}
          pitchEnabled={pitchEnabled}
          toolbarEnabled={toolbarEnabled}
        >
          {children}
        </MapView>
      </MapErrorBoundary>
    );
  } catch (error) {
    console.warn('[Map] No se pudo montar el mapa:', error);
    return fallback;
  }
}

const styles = StyleSheet.create({
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderStyle: 'dashed',
  },
  fallbackText: {
    color: '#6B7280',
    fontSize: 13,
    fontWeight: '600',
    textAlign: 'center',
    paddingHorizontal: 20,
  },
});
