import React, { memo, useEffect, useRef } from 'react';
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  Animated,
  Easing,
  Platform,
  StyleSheet,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useThemeColors } from '../../store/theme.store';
import { useUserStore } from '../../store/user.store';

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
];

const TabIcon = memo(
  ({
    focused,
    color,
    icon,
    iconFocused,
    compact,
  }: {
    focused: boolean;
    color: string;
    icon: IoniconName;
    iconFocused: IoniconName;
    compact: boolean;
  }) => {
    const scaleAnim = useRef(new Animated.Value(focused ? 1.1 : 1)).current;
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
    }, [focused, opacityAnim, scaleAnim]);

    return (
      <View style={[styles.iconContainer, compact && styles.iconContainerCompact]}>
        <Animated.View
          style={[
            styles.activePill,
            {
              backgroundColor: `${color}15`,
              opacity: opacityAnim,
            },
          ]}
        />
        <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
          <Ionicons
            name={focused ? iconFocused : icon}
            size={compact ? 27 : 30}
            color={color}
          />
        </Animated.View>
      </View>
    );
  }
);

export default function TabsLayout() {
  const theme = useThemeColors();
  const user = useUserStore((state) => state.user);
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const compact = width < 380;
  const horizontalInset = compact ? 12 : 20;
  const safeBottom = Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0);
  const tabBarBottom = safeBottom + (Platform.OS === 'ios' ? 8 : 6);
  const tabBarHeight = compact ? 58 : 62;
  const socialActive = Boolean(user?.is_social_active || user?.modo_social);

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
          borderRadius: 60,
          backgroundColor: '#FFFFFF',
          borderTopWidth: 0,
          paddingTop: 2,
          paddingBottom: Platform.OS === 'android' ? 2 : 4,
          elevation: Platform.OS === 'android' ? 12 : 6,
          shadowColor: '#111827',
          shadowOffset: { width: 0, height: 8 },
          ...Platform.select({
            ios: {
              shadowOpacity: 0.06,
              shadowRadius: 16,
            },
          }),
        },
        tabBarItemStyle: {
          justifyContent: 'center',
          alignItems: 'center',
          paddingVertical: 2,
        },
        tabBarIconStyle: {
          width: '100%',
          height: '100%',
          justifyContent: 'center',
          alignItems: 'center',
        },
        tabBarActiveTintColor: theme.primary,
        tabBarInactiveTintColor: '#1F2937',
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
                compact={compact}
              />
            ),
          }}
        />
      ))}
      <Tabs.Screen
        name="social"
        options={{
          href: socialActive ? undefined : null,
          tabBarStyle: { display: 'none' },
          tabBarIcon: ({ focused, color }) => (
            <TabIcon
              focused={focused}
              color={color}
              icon="people-outline"
              iconFocused="people"
              compact={compact}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          href: null,
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  iconContainer: {
    width: 56,
    height: 48,
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
  },
  iconContainerCompact: {
    width: 50,
    height: 46,
  },
  activePill: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    right: 0,
    borderRadius: 16,
  },
});
