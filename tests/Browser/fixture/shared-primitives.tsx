import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Toggle } from '@/components/ui/toggle';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export function SharedPrimitives() {
    const [checked, setChecked] = useState(false);
    const [mixed, setMixed] = useState<boolean | 'indeterminate'>(
        'indeterminate',
    );
    const [invalid, setInvalid] = useState(false);
    const [menuChoice, setMenuChoice] = useState('compact');
    const [showDates, setShowDates] = useState(false);

    return (
        <section
            aria-labelledby="shared-heading"
            className="grid min-w-0 gap-6"
        >
            <h2 id="shared-heading" className="text-xl">
                Shared interactive components
            </h2>
            <div className="grid gap-3">
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="shared-checkbox"
                        checked={checked}
                        onCheckedChange={(value) => {
                            setChecked(value === true);
                            if (value === true) setInvalid(false);
                        }}
                        aria-invalid={invalid}
                        aria-describedby={
                            invalid
                                ? 'shared-checkbox-help shared-checkbox-error'
                                : 'shared-checkbox-help'
                        }
                    />
                    <Label htmlFor="shared-checkbox">
                        Include synthetic example
                    </Label>
                </div>
                <p
                    id="shared-checkbox-help"
                    className="text-sm text-muted-foreground"
                >
                    This changes only local fixture state.
                </p>
                <InputError
                    id="shared-checkbox-error"
                    message={
                        invalid ? 'Choose the synthetic example.' : undefined
                    }
                />
                <Button variant="outline" onClick={() => setInvalid(!checked)}>
                    Validate synthetic choice
                </Button>
                {invalid && (
                    <Alert id="shared-error" variant="destructive">
                        <AlertTitle>Review synthetic choice</AlertTitle>
                        <AlertDescription>
                            Choose the synthetic example.
                        </AlertDescription>
                    </Alert>
                )}
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="shared-mixed"
                        checked={mixed}
                        onCheckedChange={setMixed}
                    />
                    <Label htmlFor="shared-mixed">
                        Partially selected examples
                    </Label>
                </div>
                <div className="flex items-center gap-3">
                    <Checkbox id="shared-disabled" disabled />
                    <Label htmlFor="shared-disabled">
                        Unavailable checkbox
                    </Label>
                </div>
                <Button variant="outline">After unavailable checkbox</Button>
            </div>
            <div className="flex flex-wrap items-center gap-4">
                <Toggle variant="outline">Pin example</Toggle>
                <Toggle disabled>Unavailable pin</Toggle>
                <ToggleGroup
                    type="single"
                    defaultValue="usd"
                    variant="outline"
                    aria-label="Synthetic view"
                >
                    <ToggleGroupItem value="usd">USD view</ToggleGroupItem>
                    <ToggleGroupItem value="unavailable" disabled>
                        Unavailable view
                    </ToggleGroupItem>
                    <ToggleGroupItem value="inr">INR view</ToggleGroupItem>
                </ToggleGroup>
            </div>
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button variant="outline">
                            Explain recorded activity
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        These are synthetic observations used to test shared
                        components, not verified household cash flow.
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <Collapsible>
                <CollapsibleTrigger asChild>
                    <Button variant="outline">Show example details</Button>
                </CollapsibleTrigger>
                <CollapsibleContent
                    role="region"
                    aria-label="Synthetic expanded details"
                    className="grid gap-3 py-3"
                >
                    <p>Synthetic details are available when expanded.</p>
                    <Label htmlFor="expanded-note">Expanded note</Label>
                    <Input id="expanded-note" />
                </CollapsibleContent>
            </Collapsible>
            <Collapsible disabled>
                <CollapsibleTrigger asChild>
                    <Button variant="outline">Unavailable disclosure</Button>
                </CollapsibleTrigger>
                <CollapsibleContent>Unavailable content</CollapsibleContent>
            </Collapsible>
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="outline">Open example sheet</Button>
                </SheetTrigger>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Example panel</SheetTitle>
                        <SheetDescription>
                            Synthetic content, without backend actions.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-2 px-4">
                        <Label htmlFor="panel-note">Panel note</Label>
                        <Input id="panel-note" />
                    </div>
                    <SheetFooter>
                        <SheetClose asChild>
                            <Button variant="outline">Finish panel</Button>
                        </SheetClose>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">Example display options</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuLabel>Fixture display</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={menuChoice}
                        onValueChange={setMenuChoice}
                    >
                        <DropdownMenuRadioItem value="compact">
                            Compact view
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="unavailable" disabled>
                            Unavailable density
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="comfortable">
                            Comfortable view
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                    <DropdownMenuCheckboxItem
                        checked={showDates}
                        onCheckedChange={(value) =>
                            setShowDates(value === true)
                        }
                    >
                        Show example dates
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <NavigationMenu aria-label="Synthetic navigation" delayDuration={0}>
                <NavigationMenuList>
                    <NavigationMenuItem>
                        <NavigationMenuTrigger>
                            Example links
                        </NavigationMenuTrigger>
                        <NavigationMenuContent className="grid gap-2">
                            <NavigationMenuLink href="#fields-heading">
                                Example fields
                            </NavigationMenuLink>
                            <NavigationMenuLink href="#primitives-heading">
                                Example controls
                            </NavigationMenuLink>
                        </NavigationMenuContent>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                        <NavigationMenuLink href="#password-heading">
                            Example password
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>
        </section>
    );
}
