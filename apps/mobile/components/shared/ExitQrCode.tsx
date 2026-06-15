import React, { useMemo } from 'react';
import Svg, { Rect } from 'react-native-svg';

type ExitQrCodeProps = {
  value: string;
  size?: number;
};

const VERSION = 4;
const MODULE_COUNT = 21 + (VERSION - 1) * 4;
const DATA_CODEWORDS = 80;
const EC_CODEWORDS = 20;
const ALIGNMENT_CENTER = 26;

export function ExitQrCode({ value, size = 240 }: ExitQrCodeProps) {
  const matrix = useMemo(() => generateMatrix(value), [value]);
  const quiet = 4;
  const totalModules = MODULE_COUNT + quiet * 2;
  const moduleSize = size / totalModules;

  return (
    <Svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
      <Rect width={size} height={size} fill="#FFFFFF" />
      {matrix.flatMap((row, y) =>
        row.map((dark, x) =>
          dark ? (
            <Rect
              key={`${x}-${y}`}
              x={(x + quiet) * moduleSize}
              y={(y + quiet) * moduleSize}
              width={Math.ceil(moduleSize)}
              height={Math.ceil(moduleSize)}
              fill="#111827"
            />
          ) : null
        )
      )}
    </Svg>
  );
}

function generateMatrix(value: string): boolean[][] {
  const bytes = utf8Bytes(value);
  if (bytes.length > 78) {
    throw new Error('El contenido del QR excede la capacidad del pase de salida.');
  }

  const matrix: (boolean | null)[][] = Array.from({ length: MODULE_COUNT }, () =>
    Array.from({ length: MODULE_COUNT }, () => null)
  );
  const reserved: boolean[][] = Array.from({ length: MODULE_COUNT }, () =>
    Array.from({ length: MODULE_COUNT }, () => false)
  );

  const setModule = (x: number, y: number, dark: boolean, reserve = true) => {
    if (x < 0 || y < 0 || x >= MODULE_COUNT || y >= MODULE_COUNT) return;
    matrix[y][x] = dark;
    if (reserve) reserved[y][x] = true;
  };

  drawFinder(setModule, 0, 0);
  drawFinder(setModule, MODULE_COUNT - 7, 0);
  drawFinder(setModule, 0, MODULE_COUNT - 7);
  drawAlignment(setModule, ALIGNMENT_CENTER, ALIGNMENT_CENTER);

  for (let i = 8; i < MODULE_COUNT - 8; i += 1) {
    setModule(i, 6, i % 2 === 0);
    setModule(6, i, i % 2 === 0);
  }

  reserveFormatAreas(matrix, reserved);
  setModule(8, 4 * VERSION + 9, true);

  const codewords = createCodewords(bytes);
  const bits = codewords.flatMap((byte) =>
    Array.from({ length: 8 }, (_item, index) => (byte >> (7 - index)) & 1)
  );

  let bitIndex = 0;
  let upward = true;
  for (let col = MODULE_COUNT - 1; col > 0; col -= 2) {
    if (col === 6) col -= 1;
    for (let rowOffset = 0; rowOffset < MODULE_COUNT; rowOffset += 1) {
      const y = upward ? MODULE_COUNT - 1 - rowOffset : rowOffset;
      for (let dx = 0; dx < 2; dx += 1) {
        const x = col - dx;
        if (reserved[y][x]) continue;

        let bit = bits[bitIndex] ?? 0;
        bitIndex += 1;
        if ((x + y) % 2 === 0) bit ^= 1;
        matrix[y][x] = bit === 1;
      }
    }
    upward = !upward;
  }

  drawFormatBits(setModule);

  return matrix.map((row) => row.map(Boolean));
}

function drawFinder(setModule: (x: number, y: number, dark: boolean, reserve?: boolean) => void, x: number, y: number) {
  for (let dy = -1; dy <= 7; dy += 1) {
    for (let dx = -1; dx <= 7; dx += 1) {
      const xx = x + dx;
      const yy = y + dy;
      const isFinder =
        dx >= 0 &&
        dx <= 6 &&
        dy >= 0 &&
        dy <= 6 &&
        (dx === 0 || dx === 6 || dy === 0 || dy === 6 || (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4));
      setModule(xx, yy, isFinder);
    }
  }
}

function drawAlignment(setModule: (x: number, y: number, dark: boolean, reserve?: boolean) => void, cx: number, cy: number) {
  for (let dy = -2; dy <= 2; dy += 1) {
    for (let dx = -2; dx <= 2; dx += 1) {
      const distance = Math.max(Math.abs(dx), Math.abs(dy));
      setModule(cx + dx, cy + dy, distance === 2 || distance === 0);
    }
  }
}

function reserveFormatAreas(matrix: (boolean | null)[][], reserved: boolean[][]) {
  const reserve = (x: number, y: number) => {
    if (matrix[y][x] === null) matrix[y][x] = false;
    reserved[y][x] = true;
  };

  for (let i = 0; i <= 8; i += 1) {
    if (i !== 6) {
      reserve(8, i);
      reserve(i, 8);
    }
  }
  for (let i = 0; i < 8; i += 1) {
    reserve(MODULE_COUNT - 1 - i, 8);
    reserve(8, MODULE_COUNT - 1 - i);
  }
}

function drawFormatBits(setModule: (x: number, y: number, dark: boolean, reserve?: boolean) => void) {
  const bits = getFormatBits();
  const bit = (index: number) => ((bits >> index) & 1) === 1;

  for (let i = 0; i <= 5; i += 1) setModule(8, i, bit(i));
  setModule(8, 7, bit(6));
  setModule(8, 8, bit(7));
  setModule(7, 8, bit(8));
  for (let i = 9; i < 15; i += 1) setModule(14 - i, 8, bit(i));

  for (let i = 0; i < 8; i += 1) setModule(MODULE_COUNT - 1 - i, 8, bit(i));
  for (let i = 8; i < 15; i += 1) setModule(8, MODULE_COUNT - 15 + i, bit(i));
  setModule(8, MODULE_COUNT - 8, true);
}

function getFormatBits() {
  const eccLow = 1;
  const mask = 0;
  const data = (eccLow << 3) | mask;
  let bits = data << 10;
  for (let i = 14; i >= 10; i -= 1) {
    if (((bits >> i) & 1) !== 0) {
      bits ^= 0x537 << (i - 10);
    }
  }
  return ((data << 10) | bits) ^ 0x5412;
}

function createCodewords(bytes: number[]) {
  const bitBuffer: number[] = [];
  appendBits(bitBuffer, 0b0100, 4);
  appendBits(bitBuffer, bytes.length, 8);
  bytes.forEach((byte) => appendBits(bitBuffer, byte, 8));
  appendBits(bitBuffer, 0, Math.min(4, DATA_CODEWORDS * 8 - bitBuffer.length));
  while (bitBuffer.length % 8 !== 0) appendBits(bitBuffer, 0, 1);

  const data: number[] = [];
  for (let i = 0; i < bitBuffer.length; i += 8) {
    data.push(bitBuffer.slice(i, i + 8).reduce((acc, bit) => (acc << 1) | bit, 0));
  }
  for (let pad = 0; data.length < DATA_CODEWORDS; pad += 1) {
    data.push(pad % 2 === 0 ? 0xec : 0x11);
  }

  return data.concat(reedSolomon(data, EC_CODEWORDS));
}

function appendBits(target: number[], value: number, length: number) {
  for (let i = length - 1; i >= 0; i -= 1) {
    target.push((value >> i) & 1);
  }
}

function reedSolomon(data: number[], degree: number) {
  const { exp, log } = createGaloisTables();
  const multiply = (a: number, b: number) => {
    if (a === 0 || b === 0) return 0;
    return exp[log[a] + log[b]];
  };

  const divisor = Array.from({ length: degree }, () => 0);
  divisor[degree - 1] = 1;
  let root = 1;
  for (let i = 0; i < degree; i += 1) {
    for (let j = 0; j < degree; j += 1) {
      divisor[j] = multiply(divisor[j], root);
      if (j + 1 < degree) {
        divisor[j] ^= divisor[j + 1];
      }
    }
    root = multiply(root, 2);
  }

  const result = Array.from({ length: degree }, () => 0);
  data.forEach((byte) => {
    const factor = byte ^ result.shift()!;
    result.push(0);
    for (let i = 0; i < degree; i += 1) {
      result[i] ^= multiply(divisor[i], factor);
    }
  });

  return result;
}

function createGaloisTables() {
  const exp = Array.from({ length: 512 }, () => 0);
  const log = Array.from({ length: 256 }, () => 0);
  let value = 1;
  for (let i = 0; i < 255; i += 1) {
    exp[i] = value;
    log[value] = i;
    value <<= 1;
    if ((value & 0x100) !== 0) value ^= 0x11d;
  }
  for (let i = 255; i < 512; i += 1) {
    exp[i] = exp[i - 255];
  }

  return { exp, log };
}

function utf8Bytes(value: string) {
  const bytes: number[] = [];
  for (let i = 0; i < value.length; i += 1) {
    let codePoint = value.charCodeAt(i);
    if (codePoint >= 0xd800 && codePoint <= 0xdbff && i + 1 < value.length) {
      const next = value.charCodeAt(i + 1);
      if (next >= 0xdc00 && next <= 0xdfff) {
        codePoint = 0x10000 + ((codePoint - 0xd800) << 10) + (next - 0xdc00);
        i += 1;
      }
    }

    if (codePoint < 0x80) {
      bytes.push(codePoint);
    } else if (codePoint < 0x800) {
      bytes.push(0xc0 | (codePoint >> 6), 0x80 | (codePoint & 0x3f));
    } else if (codePoint < 0x10000) {
      bytes.push(0xe0 | (codePoint >> 12), 0x80 | ((codePoint >> 6) & 0x3f), 0x80 | (codePoint & 0x3f));
    } else {
      bytes.push(
        0xf0 | (codePoint >> 18),
        0x80 | ((codePoint >> 12) & 0x3f),
        0x80 | ((codePoint >> 6) & 0x3f),
        0x80 | (codePoint & 0x3f)
      );
    }
  }
  return bytes;
}
