import React from 'react';
import Svg, { Path } from 'react-native-svg';

type GoogleGIconProps = {
  size?: number;
};

export function GoogleGIcon({ size = 22 }: GoogleGIconProps) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24">
      <Path
        fill="#4285F4"
        d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.29h6.46c-.28 1.5-1.13 2.77-2.41 3.62v3.01h3.91c2.29-2.11 3.53-5.21 3.53-8.65z"
      />
      <Path
        fill="#34A853"
        d="M12 24c3.24 0 5.96-1.07 7.95-2.91l-3.91-3.01c-1.09.73-2.47 1.16-4.04 1.16-3.12 0-5.77-2.11-6.72-4.95H1.24v3.1C3.22 21.31 7.27 24 12 24z"
      />
      <Path
        fill="#FBBC05"
        d="M5.28 14.29c-.24-.73-.38-1.5-.38-2.29s.14-1.56.38-2.29v-3.1H1.24C.45 8.2 0 10.05 0 12s.45 3.8 1.24 5.39l4.04-3.1z"
      />
      <Path
        fill="#EA4335"
        d="M12 4.76c1.76 0 3.34.61 4.59 1.79l3.45-3.45C17.95 1.16 15.23 0 12 0 7.27 0 3.22 2.69 1.24 6.61l4.04 3.1C6.23 6.87 8.88 4.76 12 4.76z"
      />
    </Svg>
  );
}
