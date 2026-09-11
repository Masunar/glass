/**
 * Kontrola typow kodu aplikacji.
 *
 * `tsc` sprawdza caly program, a nie tylko pliki wskazane w `include`:
 * ekrany aplikacji importuja `@salvon/*`, wiec framework wchodzi do
 * sprawdzenia razem z nimi. Siedzi w nim dwanascie wlasnych bledow
 * typow, ktore nie pochodza z tego repozytorium i nie sa tu do
 * naprawienia — a przez nie kazdy pull request mial czerwony front,
 * niezaleznie od tego, co zmieniono w `app/`.
 *
 * Rozwiazanie jest takie samo, jakie juz podjeto przy ESLincie
 * (`eslint app/src`, nie caly projekt): o wyniku decyduja wylacznie
 * bledy w kodzie aplikacji. Bledy Salvona nie znikaja — sa wypisywane
 * na koniec jako dlug, zeby nie przestaly istniec tylko dlatego, ze CI
 * jest zielone.
 *
 * Pelna kontrola bez tego podzialu: `npm run typecheck:all`.
 */
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const tsc = fileURLToPath(new URL('../node_modules/typescript/bin/tsc', import.meta.url));

const result = spawnSync(process.execPath, [tsc, '--noEmit', '--pretty', 'false'], {
  encoding: 'utf8',
});

const output = `${result.stdout ?? ''}${result.stderr ?? ''}`;
const startsError = (line) => /^\S.*\(\d+,\d+\): error TS\d+/.test(line);

// Blad w tsc to jedna linia naglowka i dowolna liczba wcietych linii
// z wyjasnieniem. Grupujemy je, zeby wypisac blad w calosci albo wcale.
const blocks = [];

for (const line of output.split('\n')) {
  if (startsError(line)) {
    blocks.push([line]);
    continue;
  }

  if (blocks.length > 0 && /^\s+\S/.test(line)) {
    blocks[blocks.length - 1].push(line);
  }
}

const isFramework = (block) => block[0].startsWith('salvon/');
const app = blocks.filter((block) => !isFramework(block));
const framework = blocks.filter(isFramework);

// tsc padl z powodu, ktorego nie umiemy przypisac do pliku — konfiguracja,
// brak pamieci. Takiego wyniku nie wolno przemilczec.
if (blocks.length === 0 && result.status !== 0) {
  process.stdout.write(output);
  process.exit(result.status ?? 1);
}

for (const block of app) {
  console.log(block.join('\n'));
}

if (framework.length > 0) {
  const files = [...new Set(framework.map((block) => block[0].split('(')[0]))];

  console.log('');
  console.log(`Dlug typow w salvon/: ${framework.length} w ${files.length} plikach.`);
  console.log(files.map((file) => `  ${file}`).join('\n'));
  console.log('Nie blokuja CI. Pelna lista: npm run typecheck:all');
}

if (app.length > 0) {
  console.log('');
  console.log(`Bledy typow w kodzie aplikacji: ${app.length}.`);
  process.exit(1);
}

console.log('');
console.log('Kod aplikacji bez bledow typow.');
