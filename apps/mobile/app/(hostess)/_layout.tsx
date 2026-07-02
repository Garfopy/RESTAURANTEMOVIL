import React from 'react';
import { Tabs } from 'expo-router';
import { StaffTabBar, type StaffTabItem } from '../../components/staff/StaffTabBar';

const TABS: Record<string, StaffTabItem> = {
  index: { label: 'Panel', icon: 'qr-code-outline', activeIcon: 'qr-code' },
  tables: { label: 'Mesas', icon: 'grid-outline', activeIcon: 'grid' },
};

export default function HostessLayout() {
  return (
    <Tabs
      tabBar={(props) => <StaffTabBar {...props} items={TABS} accent="#17172F" />}
      screenOptions={{
        headerShown: false,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Panel',
        }}
      />
      <Tabs.Screen
        name="tables"
        options={{
          title: 'Mesas',
        }}
      />
    </Tabs>
  );
}
