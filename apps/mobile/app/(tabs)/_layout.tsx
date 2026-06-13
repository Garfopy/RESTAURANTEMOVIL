import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  Platform,
  View,
  StyleSheet,
  Animated,
  Easing,
} from 'react-native';
import React, { useRef, useEffect, memo } from 'react';
import { Colors } from '../../theme';

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
          left: 20,
          right: 20,
          bottom: Platform.OS === 'ios' ? 28 : 16,
          height: 64,
          borderRadius: 24,
          backgroundColor: '#FFFFFF',
          borderTopWidth: 0,
          elevation: 6,
          paddingBottom: 0,
          
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
        },

        tabBarIconStyle: {
          width: '100%',
          height: '100%',
          justifyContent: 'center',
          alignItems: 'center',
        },

        // --- CAMBIO CLAVE AQUÍ PARA QUE LOS ICONOS SE VEAN MÁS NEGROS ---
        tabBarActiveTintColor: '#000000',   // Negro puro para el icono seleccionado
        tabBarInactiveTintColor: '#1F2937', // Gris muy oscuro (casi negro) para los no seleccionados
        // -------------------------------------------------------------
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
