import https from 'https';

interface GoogleTokenInfo {
  sub: string;
  email: string;
  name: string;
  picture: string;
  email_verified: string;
  aud: string;
  exp: string;
  iat: string;
}

/**
 * Verifica un Google ID Token llamando al endpoint de Google.
 * No requiere ninguna librería externa, solo HTTPS nativo.
 */
export function verifyGoogleToken(idToken: string): Promise<GoogleTokenInfo> {
  return new Promise((resolve, reject) => {
    const url = `https://oauth2.googleapis.com/tokeninfo?id_token=${encodeURIComponent(idToken)}`;

    https
      .get(url, (res) => {
        let data = '';
        res.on('data', (chunk: string) => { data += chunk; });
        res.on('end', () => {
          try {
            const info: GoogleTokenInfo = JSON.parse(data);

            if ('error_description' in info || !info.sub) {
              reject(new Error('Token de Google inválido'));
              return;
            }

            const expectedAud = process.env.GOOGLE_CLIENT_ID;
            if (expectedAud && info.aud !== expectedAud) {
              reject(new Error('Token no pertenece a esta aplicación'));
              return;
            }

            if (info.email_verified !== 'true') {
              reject(new Error('Email no verificado en Google'));
              return;
            }

            const expTs = parseInt(info.exp) * 1000;
            if (expTs < Date.now()) {
              reject(new Error('Token de Google expirado'));
              return;
            }

            resolve(info);
          } catch {
            reject(new Error('Respuesta inválida de Google tokeninfo'));
          }
        });
      })
      .on('error', (err) => reject(err));
  });
}
