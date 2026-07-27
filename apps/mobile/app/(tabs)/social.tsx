import React from 'react';
import { useUserStore } from '../../store/user.store';
import { AuthRequiredState } from '../../components/auth/AuthRequiredState';
import SocialScreen from '../profile/social';

export default function SocialTabScreen() {
  const token = useUserStore((state) => state.token);

  if (!token) {
    return (
      <AuthRequiredState
        icon="people-outline"
        title="Regístrate para activar el modo social"
        message="Crea tu perfil para conectar dentro del restaurante, enviar momentos y recibir beneficios sociales."
        benefits={['Perfil social', 'Momentos', 'Beneficios']}
        returnTo="/(tabs)/social"
      />
    );
  }

  return <SocialScreen />;
}
