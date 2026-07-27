import React from 'react';
import { useUserStore } from '../../store/user.store';
import { AuthRequiredState } from '../../components/auth/AuthRequiredState';
import SocialScreen from '../profile/social';

export default function SocialTabScreen() {
  const token = useUserStore((state) => state.token);

  if (!token) {
    return (
      <AuthRequiredState
        title="Modo social"
        message="Inicia sesion para activar tu perfil social y conectar dentro del restaurante."
        returnTo="/(tabs)/social"
      />
    );
  }

  return <SocialScreen />;
}
