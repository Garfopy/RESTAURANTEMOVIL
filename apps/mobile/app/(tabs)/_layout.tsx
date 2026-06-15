import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  Platform,
  View,
  StyleSheet,
  Animated,
  Easing,
  useWindowDimensions,
} from 'react-native';
import React, { useRef, useEffect, memo } from 'react';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Colors } from '../../theme';
import { useThemeColors } from '../../store/theme.store';

type IoniconName = keyof typeof Ionicons.glyphMap;

const TABS: {
  name: string;
  icon: IoniconName;
  iconFocused: IoniconName;
}[] = [
  { name: 'index', icon: 'home-outline', iconFocused: 'home' },
  { name: 'orders', icon: 'bag-outline', iconFocused: 'bag' },
  { name: 'favorites', icon: 'heart-outline', iconFocused: 'heart' },
  { name: 'promotions', icon: 'pricetag-outline', iconFocused: 'pricetag' },
  { name: 'profile', icon: 'person-circle-outline', iconFocused: 'person-circle' },
];

const TabIcon = memo(
  ({
    focused,
    color,
    icon,
    iconFocused,
  }: {
    focused: boolean;
    color: string;
    icon: IoniconName;
    iconFocused: IoniconName;
  }) => {
    // Animación de escala para el ícono
    const scaleAnim = useRef(new Animated.Value(focused ? 1.1 : 1)).current;
    // Animación de opacidad para el fondo (pill)
    const opacityAnim = useRef(new Animated.Value(focused ? 1 : 0)).current;

    useEffect(() => {
      Animated.parallel([
        Animated.timing(scaleAnim, {
          toValue: focused ? 1.1 : 1,
          duration: 180,
          easing: Easing.out(Easing.back(1.5)),
          useNativeDriver: true,
        }),
        Animated.timing(opacityAnim, {
          toValue: focused ? 1 : 0,
          duration: 140,
          easing: Easing.out(Easing.ease),
          useNativeDriver: true,
        }),
      ]).start();
    }, [focused]);

    return (
      <View style={styles.iconContainer}>
        {/* Píldora de fondo suave (REVERTIDO EL DISEÑO ANTERIOR) */}
        <Animated.View
          style={[
            styles.activePill,
            {
              // Volvemos a un fondo transparente muy suave
              backgroundColor: `${color}15`, 
              opacity: opacityAnim,
            },
          ]}
        />
        {/* Contenedor animado para el ícono */}
        <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
          <Ionicons
            name={focused ? iconFocused : icon}
            size={30} // Tamaño grande (como pediste antes)
            color={color} // Este color ahora será muy oscuro
          />
        </Animated.View>
      </View>
    );
  }
);

export default function TabsLayout() {
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const horizontalInset = width < 380 ? 12 : 20;
  const tabBarBottom = insets.bottom + (Platform.OS === 'ios' ? 8 : 10);
  const tabBarHeight = 64 + Math.min(insets.bottom, 10);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarShowLabel: false,
        tabBarAllowFontScaling: false,
        lazy: true,
        freezeOnBlur: true,

        tabBarStyle: {
          position: 'absolute',
          left: horizontalInset,
          right: horizontalInset,
          bottom: tabBarBottom,
          height: tabBarHeight,
          borderRadius: 24,
          backgroundColor: '#FFFFFF',
          borderTopWidth: 0,
          elevation: 6,
          paddingBottom: Math.min(insets.bottom, 8),
          paddingTop: 4,
          
          ...Platform.select({
            ios: {
              shadowColor: '#111827',
              shadowOffset: { width: 0, height: 8 },
              shadowOpacity: 0.06,
              shadowRadius: 16,
            },
          }),
        },

        tabBarItemStyle: {
          justifyContent: 'center',
          alignItems: 'center',
          paddingVertical: 4,
        },

        tabBarIconStyle: {
          width: '100%',
          height: '100%',
          justifyContent: 'center',
          alignItems: 'center',
        },

        tabBarActiveTintColor: theme.primary,
        tabBarInactiveTintColor: '#1F2937', // Gris muy oscuro (casi negro) para los no seleccionados
      }}
    >
      {TABS.map((tab) => (
        <Tabs.Screen
          key={tab.name}
          name={tab.name}
          options={{
            tabBarIcon: ({ focused, color }) => (
              <TabIcon
                focused={focused}
                color={color}
                icon={tab.icon}
                iconFocused={tab.iconFocused}
              />
            ),
          }}
        />
      ))}
    </Tabs>
  );
}

const styles = StyleSheet.create({
  iconContainer: {
    // Tamaños ajustados para icono de 32px
    width: 60,
    height: 48,
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
  },
  activePill: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    right: 0,
    borderRadius: 16,
    // (SE ELIMINARON BORDE Y FONDO SÓLIDO ANTERIOR)
  },
});
