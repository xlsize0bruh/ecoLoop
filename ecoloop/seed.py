"""Isolated demo fixtures; never seed a production community."""
from .domain import EcoLoop, CATALOG


def seed_demo(data):
    app = EcoLoop(data)
    if data['users']:
        return app
    password = 'demo-only-not-for-production'
    members = {}
    for key, name in [('maker', 'Pratyush'), ('asha', 'Asha'), ('kabir', 'Kabir'), ('mira', 'Mira'), ('organiser', 'Community Organiser')]:
        members[key] = app.create_user(dict(name=name, email=key+'@ecoloop.example', password=password),
                                       'organiser' if key == 'organiser' else 'member')
    def create(who, title, material, credits, quantity=1, unit='piece', category='Materials', **extra):
        value = dict(title=title, material=material, credits=credits, quantity=quantity, unit=unit,
                     category=category, condition='good', mode='credits',
                     description='Demo listing. A useful item looking for its next home. Check the condition with the owner before collection.')
        value.update(extra)
        return app.create_item(members[who], value)
    create('asha', 'Cardboard sheets · pack of 3', 'cardboard_sheet', 20, 2, 'bundle', width=45, height=35, thickness=3,
           description='Three clean, sturdy panels per bundle. Each panel is 45 × 35 cm, 3 mm thick. Ideal for a desktop organiser or display.')
    create('kabir', 'Cardboard tubes · pack of 3', 'cardboard_tube', 15, 2, 'bundle', width=4, height=12)
    create('mira', 'Fabric scraps & cotton string', 'fabric', 25, 3, 'bundle', 'Art & craft', width=40, height=30)
    create('asha', 'The little book of big ideas', 'book', 20, category='Books', condition='excellent')
    create('kabir', 'A geometry box for your next class', 'stationery', 20, category='Stationery')
    create('mira', 'Unmarked drawing paper', 'paper', 3, 12, 'sheet', 'Art & craft', width=29.7, height=21, condition='new')
    create('asha', 'Small pine offcuts', 'wood', 8, 4, width=20, height=10, thickness=10)
    create('kabir', 'Clean wide-mouth containers', 'plastic', 5, 3, category='Home & living', width=10, height=15)
    create('mira', 'A notebook with room for ideas', 'stationery', 0, category='Stationery', mode='gift', condition='new')
    create('asha', 'Stories for a rainy afternoon', 'book', 0, category='Books', mode='barter')
    create('maker', 'A novel ready for another reader', 'book', 25, category='Books', condition='excellent')
    c = app.contribute(members['mira'], {'items': [dict(title='A spare set of coloured pencils',
        description='Demo shared-stock item. Twelve usable coloured pencils in a reusable box.',
        category='Art & craft', material='stationery', quantity=1, unit='bundle', credits=30, condition='good')]})
    app.review_contribution(members['organiser'], c['id'], dict(decision='accept', physically_received=True,
                                                               note='Demo intake: inspected at the exchange desk.'))
    t = CATALOG['templates'][0]
    app.create_project(members['maker'], dict(name=t['project_name'], description=t['description'],
                                             already_have=t['already_have'], requirements=t['requirements']))
    return app
