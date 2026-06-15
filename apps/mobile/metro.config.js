const { getDefaultConfig } = require('expo/metro-config');
const { withNativeWind } = require('nativewind/metro');
const path = require('path');

const config = getDefaultConfig(__dirname);

// Fix Windows pnpm symlink path duplication (c:\C:\ADS\... -> C:\ADS\...)
// This happens because pnpm's virtual store uses symlinks that Metro
// sometimes resolves incorrectly on Windows
const originalResolveRequest = config.resolver.resolveRequest;
config.resolver.resolveRequest = (context, moduleName, platform) => {
  const result = originalResolveRequest
    ? originalResolveRequest(context, moduleName, platform)
    : context.resolveRequest(context, moduleName, platform);

  if (result?.filePath) {
    // Fix doubled drive letter paths like "c:\C:\Users\..."
    result.filePath = result.filePath.replace(
      /^([a-zA-Z]):\\(?:[a-zA-Z]:\\)?/,
      (match, drive) => `${drive}:\\`
    );
  }
  return result;
};

module.exports = withNativeWind(config, { input: './global.css' });
