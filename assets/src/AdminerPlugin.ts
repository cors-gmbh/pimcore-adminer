import { type IAbstractPlugin } from '@pimcore/studio-ui-bundle'
import {CORSAdminerExtension} from "./modules/adminer-extension";

export const CORSAdminerPlugin: IAbstractPlugin = {
    name: 'MainNavEntryPlugin',

    onStartup ({ moduleSystem }) {
        moduleSystem.registerModule(CORSAdminerExtension)
    }
}