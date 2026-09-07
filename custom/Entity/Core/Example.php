<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\Base;
use Areanet\PIM\Classes\Annotations as PIM;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="example_entity", indexes={
 *     @ORM\Index(name="idx_example_slug", columns={"slug"}),
 *     @ORM\Index(name="idx_example_state", columns={"state"}),
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_example_slug", columns={"slug"})
 * })
 */
class Example extends Base {

	/**
	 * Lifecycle state of the tenant.
	 * States: provisioning, active, trial_expired, suspended, deactivated
	 *
	 * trial_expired is the state we transition into when the bootstrap
	 * decision_pro trial has ended without an upgrade. The tenant can still
	 * log in and read/export data (DSGVO compliance) but cannot write —
	 * see TrialExpiryChecker + the trial-end paywall spec.
	 *
	 * @ORM\Column(type="string", length=32, options={"default": "active"})
	 * @PIM\Select(options="provisioning,active,trial_expired,suspended,deactivated")
	 */
	protected $state = 'active';

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	protected $name;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	protected $slug;

	/**
	* Example structure:
	* {
	*   "someProperties": "value"
	* }
	*
	 * @ORM\Column(type="json", nullable=true)
	 */
	protected $jsonExample;

	/**
	 * True when Stripe reports the customer as tax-exempt (reverse-charge in EU).
	 * @ORM\Column(type="boolean", options={"default": false})
	 */
	protected $boolExample = false;

}