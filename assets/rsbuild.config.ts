import { defineConfig } from '@rsbuild/core'
import { pluginReact } from '@rsbuild/plugin-react'
import { pluginModuleFederation } from '@module-federation/rsbuild-plugin'
import { pluginGenerateEntrypoints } from '@pimcore/studio-ui-bundle/rsbuild/plugins'
import path from 'path'
import fs from 'fs'
import crypto from 'crypto'
import packages from './package.json'

const repoRoot = path.resolve(__dirname, '..')

/**
 * Files outside the npm project that influence the build output.
 */
const extraConfigFiles: string[] = []

function collectSourceFiles (dir: string, files: string[] = []): string[] {
  const ignored = new Set(['node_modules', 'dist', '.rsbuild', '@mf-types'])

  let entries: fs.Dirent[]
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true })
  } catch {
    return files
  }

  for (const entry of entries) {
    if (ignored.has(entry.name)) {
      continue
    }

    const full = path.resolve(dir, entry.name)
    if (entry.isDirectory()) {
      collectSourceFiles(full, files)
    } else if (entry.isFile()) {
      files.push(full)
    }
  }

  return files
}

/**
 * The build id is the output directory, the public asset prefix and the archive name, so it
 * has to change whenever the emitted assets change — and only then. It is derived from the
 * sources of this npm project (plus the config files above) rather than random: an unchanged
 * frontend produces an unchanged id, and the packaged archive of that id is kept as is.
 */
function computeBuildId (): string {
  const hash = crypto.createHash('sha256')
  const files = [...extraConfigFiles, ...collectSourceFiles(__dirname)]
    .filter((file) => fs.existsSync(file))
    .sort()

  for (const file of files) {
    // Hash the path relative to the repo root so the id does not depend on the checkout location
    hash.update(`${path.relative(repoRoot, file).split(path.sep).join('/')}\0`)
    hash.update(fs.readFileSync(file))
  }

  return hash.digest('hex').slice(0, 32)
}

const nodeEnv = process.env.NODE_ENV
const isDevServer = nodeEnv === 'dev-server'
const env: 'development' | 'production' = nodeEnv === 'production' ? 'production' : 'development'

// PIMCORE_BUILD_ID overrides the derived id; the dev server does not emit a build.
const buildId = process.env.PIMCORE_BUILD_ID || (isDevServer ? 'dev' : computeBuildId())

// The build is shipped as src/Resources/build-dist/build-<id>.zip (see `npm run build`) and unpacked
// into src/Resources/public/studio by Pimcore's BuildArchiveExtractor at cache warmup; the expanded
// build is not committed.
const studioDir = path.resolve(repoRoot, 'src', 'Resources', 'public', 'studio')
const buildPath = path.resolve(studioDir, buildId)

// Drop build directories of previous ids, but keep the current one: rebuilding into it keeps
// unchanged assets byte-identical instead of re-emitting them.
if (fs.existsSync(studioDir)) {
  fs.readdirSync(studioDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && entry.name !== buildId)
    .forEach((entry) => {
      fs.rmSync(path.resolve(studioDir, entry.name), { recursive: true, force: true })
    })
}
fs.mkdirSync(buildPath, { recursive: true })

/**
 * studio-package-build (from @pimcore/studio-ui-bundle) expects a `.build-id` file in the
 * output directory; written after the build so a cleaning bundler cannot drop it.
 */
const pluginWriteBuildId = {
  name: 'write-build-id',
  setup (api: { onAfterBuild: (fn: () => void) => void }): void {
    api.onAfterBuild(() => {
      fs.writeFileSync(path.join(buildPath, '.build-id'), `${buildId}\n`)
    })
  }
}

const assetPrefix = '/bundles/corsadminer/studio/' + buildId

export default defineConfig({
  mode: env,
  server: {
    port: 3032,
  },
  dev: {
    ...(!isDevServer ? {assetPrefix} : {}),
    client: {
      host: 'localhost',
      port: 3032,
      protocol: 'ws'
    }
  },
  source: {
    entry: {
      main: './src/main.ts'
    },
    decorators: {
      version: 'legacy'
    }
  },
  output: {
    manifest: true,
    assetPrefix,
    distPath: {
      root: buildPath
    },
  },
  tools: {
    bundlerChain: (chain, { env }) => {
      chain.output.uniqueName('cors_adminer_bundle');
    },
  },
  plugins: [
    pluginWriteBuildId,
    pluginGenerateEntrypoints(),
    pluginReact(),
    pluginModuleFederation({
      name: 'cors_adminer_bundle',
      filename: 'static/js/remoteEntry.js',
      exposes: {
        '.': './src/plugin.ts',
      },
      dts: false,
      remotes: {
        '@pimcore/studio-ui-bundle': `promise new Promise(resolve => {
          const studioUIBundleRemoteUrl = window.StudioUIBundleRemoteUrl
          const script = document.createElement('script')

          let hasScript = false;

          document.querySelectorAll('script').forEach((el) => {
            const elPathname = el.src.replace(/https?:\\/\\/[^/]+/, '')
            const studioUIBundleRemoteUrlPathname = studioUIBundleRemoteUrl.replace(/https?:\\/\\/[^/]+/, '')

            if (elPathname === studioUIBundleRemoteUrlPathname) {
              hasScript = true;
              return;
            }
          })

          if (hasScript) {
            resolve({
              get: (request) => window['pimcore_studio_ui_bundle'].get(request),
              init: (...arg) => {
                try {
                  return window['pimcore_studio_ui_bundle'].init(...arg)
                } catch(e) {
                  console.log('remote container already initialized')
                }
              }
            })
            return
          }

          script.src = studioUIBundleRemoteUrl
          script.onload = () => {
            const proxy = {
              get: (request) => window['pimcore_studio_ui_bundle'].get(request),
              init: (...arg) => {
                try {
                  return window['pimcore_studio_ui_bundle'].init(...arg)
                } catch(e) {
                  console.log('remote container already initialized')
                }
              }
            }
            resolve(proxy)
          }
          document.head.appendChild(script);
        })
        `,
      },
      shared: {
        ...packages.dependencies,
        react: {
          singleton: true,
          eager: true,
          requiredVersion: false,
        },
        'react-dom': {
          singleton: true,
          eager: true,
          requiredVersion: false,
        }
      },
    })
  ]
})