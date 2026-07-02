import React from 'react';
import { Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';

type IconName = keyof typeof Ionicons.glyphMap;

export type StaffTabItem = {
  icon: IconName;
  activeIcon: IconName;
  label: string;
};

type StaffTabBarProps = {
  state: any;
  descriptors: any;
  navigation: any;
  items: Record<string, StaffTabItem>;
  accent?: string;
};

export function StaffTabBar({
  state,
  descriptors,
  navigation,
  items,
  accent = '#111827',
}: StaffTabBarProps) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const compact = width < 380;
  const activeRoute = state.routes[state.index];
  const visibleRoutes = state.routes.filter((route: any) => Boolean(items[route.name]));

  if (visibleRoutes.length === 0 || !items[activeRoute?.name]) {
    return null;
  }

  return (
    <View style={[styles.wrap, { paddingBottom: Math.max(insets.bottom, 10) }]}>
      <View style={[styles.bar, compact && styles.barCompact]}>
        {visibleRoutes.map((route: any) => {
          const routeIndex = state.routes.findIndex((item: any) => item.key === route.key);
          const focused = state.index === routeIndex;
          const descriptor = descriptors[route.key];
          const item = items[route.name];

          function onPress() {
            const event = navigation.emit({
              type: 'tabPress',
              target: route.key,
              canPreventDefault: true,
            });

            if (!focused && !event.defaultPrevented) {
              navigation.navigate(route.name, route.params);
            }
          }

          return (
            <Pressable
              key={route.key}
              accessibilityRole="button"
              accessibilityState={focused ? { selected: true } : {}}
              accessibilityLabel={descriptor?.options?.tabBarAccessibilityLabel}
              onPress={onPress}
              style={[styles.item, focused && { backgroundColor: accent }]}
            >
              <Ionicons
                name={focused ? item.activeIcon : item.icon}
                size={focused ? 20 : 21}
                color={focused ? '#FFFFFF' : '#64748B'}
              />
              <Text
                numberOfLines={1}
                style={[
                  styles.label,
                  compact && styles.labelCompact,
                  focused ? styles.labelActive : styles.labelIdle,
                ]}
              >
                {item.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    paddingHorizontal: 16,
    paddingTop: 8,
    backgroundColor: 'rgba(246,247,251,0.94)',
  },
  bar: {
    minHeight: 62,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 6,
    flexDirection: 'row',
    gap: 6,
    shadowColor: '#111827',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.10,
    shadowRadius: 24,
    elevation: 14,
  },
  barCompact: {
    minHeight: 58,
    padding: 5,
    gap: 5,
  },
  item: {
    flex: 1,
    borderRadius: 8,
    minWidth: 0,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 7,
    paddingHorizontal: 8,
  },
  label: {
    fontSize: 12,
    fontWeight: '900',
  },
  labelCompact: {
    fontSize: 11,
  },
  labelActive: {
    color: '#FFFFFF',
  },
  labelIdle: {
    color: '#475569',
  },
});
