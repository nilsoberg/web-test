import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['primaryOrganizationWrapper', 'globalRole'];

    static values = {
        adminRoleId: Number
    }

    connect() {
        this.toggle();
    }

    toggle() {
        const roleId = parseInt(this.globalRoleTarget.value, 10);

        if (roleId === this.adminRoleIdValue) {
            this.primaryOrganizationWrapperTarget.style.display = 'none';
        } else {
            this.primaryOrganizationWrapperTarget.style.display = 'block';
        }
    }
}
